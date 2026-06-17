<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Integration;
use App\Services\IntegrationHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13 — Screen 53: Third-Party Integration Switchboard
 * Toggle, configure, health-check all external integrations from one screen
 */
class IntegrationSwitchboardController extends Controller
{
    // Integration categories and their providers
    private const INTEGRATION_CATALOG = [
        'bureau'     => ['experian', 'crif', 'cibil', 'highmark'],
        'esign'      => ['digio', 'leegality'],
        'enach'      => ['enach_npci', 'digio_enach'],
        'penny_drop' => ['razorpay_pd', 'yesbank_pd'],
        'gst'        => ['karza_gst', 'surepass_gst'],
        'pan'        => ['karza_pan', 'surepass_pan', 'nsdl_pan'],
        'aadhaar'    => ['uidai', 'karza_aadhaar'],
        'sms'        => ['msg91', 'kaleyra'],
        'email'      => ['ses', 'sendgrid'],
        'whatsapp'   => ['meta_wa', 'interakt'],
        'storage'    => ['cloudflare_r2', 'aws_s3'],
    ];

    public function __construct(private IntegrationHealthService $healthService)
    {
        $this->middleware('permission:system.integrations.view')
            ->only(['index', 'show', 'healthCheck', 'healthCheckAll', 'billingSummary']);

        $this->middleware('permission:system.integrations.edit')
            ->only(['update', 'setPrimary']);

        $this->middleware('permission:system.integrations.toggle')
            ->only(['toggle']);
    }

    /**
     * GET /api/admin/integrations
     * Full integration registry with live health status
     */
    public function index()
    {
        $integrations = Integration::orderBy('category')->orderBy('name')->get();

        $integrations->each(function ($integration) {
            $integration->health = Cache::get("integration.health.{$integration->id}", [
                'status'           => 'unknown',
                'last_checked_at'  => null,
                'last_success_at'  => null,
                'response_time_ms' => null,
            ]);

            $integration->api_key    = $this->maskSecret($this->decryptCredential($integration->api_key_enc));
            $integration->api_secret = $this->maskSecret($this->decryptCredential($integration->api_secret_enc));
        });

        return response()->json([
            'success' => true,
            'data'    => $integrations->groupBy('category'),
            'catalog' => self::INTEGRATION_CATALOG,
        ]);
    }

    /**
     * GET /api/admin/integrations/{id}
     * Single integration detail — credentials visible to super admin only
     */
    public function show(int $id)
    {
        $integration = Integration::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => array_merge($integration->toArray(), [
                'api_key'         => $this->decryptCredential($integration->api_key_enc),
                'api_secret'      => $this->decryptCredential($integration->api_secret_enc),
                'health'          => Cache::get("integration.health.{$id}", []),
                'monthly_calls'   => $this->getMonthlyCallCount($id),
                'monthly_cost'    => $this->getMonthlyCost($id),
                'rotation_due_at' => $integration->credential_rotation_due_at,
            ]),
        ]);
    }

    /**
     * PUT /api/admin/integrations/{id}
     * Update integration credentials, endpoints, active/fallback provider
     */
    public function update(Request $request, int $id)
    {
        $integration = Integration::findOrFail($id);

        $validated = $request->validate([
            'base_url'        => 'sometimes|url',
            'api_key'         => 'sometimes|string',
            'api_secret'      => 'sometimes|string',
            'webhook_url'     => 'nullable|url',
            'is_active'       => 'nullable|boolean',
            'is_fallback'     => 'nullable|boolean',
            'priority'        => 'nullable|integer|min:1|max:10',
            'timeout_seconds' => 'nullable|integer|min:1|max:120',
            'retry_attempts'  => 'nullable|integer|min:0|max:5',
            'notes'           => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($integration, $validated) {
            $updateData = collect($validated)
                ->except(['api_key', 'api_secret'])
                ->all();

            if (isset($validated['api_key'])) {
                $updateData['api_key_enc'] = Crypt::encryptString($validated['api_key']);
                $updateData['credential_rotation_due_at'] = now()->addDays(90);
            }

            if (isset($validated['api_secret'])) {
                $updateData['api_secret_enc'] = Crypt::encryptString($validated['api_secret']);
            }

            $integration->update($updateData);
        });

        $this->audit('integration_updated', [
            'integration_id'   => $integration->id,
            'integration_name' => $integration->name,
        ]);

        return response()->json(['success' => true, 'message' => 'Integration updated.']);
    }

    /**
     * POST /api/admin/integrations/{id}/toggle
     * Enable or disable an integration instantly
     */
    public function toggle(Request $request, int $id)
    {
        $integration = Integration::findOrFail($id);
        $newStatus   = ! $integration->is_active;

        $integration->update(['is_active' => $newStatus]);

        if (! $newStatus && $integration->is_primary) {
            Integration::where('category', $integration->category)
                ->where('is_fallback', true)
                ->update(['is_primary' => true]);
        }

        $this->audit($newStatus ? 'integration_enabled' : 'integration_disabled', [
            'integration_id'   => $integration->id,
            'integration_name' => $integration->name,
        ]);

        return response()->json([
            'success'   => true,
            'is_active' => $newStatus,
            'message'   => $newStatus
                ? "{$integration->name} enabled."
                : "{$integration->name} disabled. Fallback promoted if configured.",
        ]);
    }

    /**
     * POST /api/admin/integrations/{id}/health-check
     * Trigger a live health check with test payload
     */
    public function healthCheck(int $id)
    {
        $integration = Integration::findOrFail($id);
        $result      = $this->healthService->check($integration);

        Cache::put("integration.health.{$id}", $result, 300);

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/admin/integrations/health-check-all
     * Run health check on all integrations
     */
    public function healthCheckAll()
    {
        $integrations = Integration::where('is_active', true)->get();
        $results      = [];

        foreach ($integrations as $integration) {
            $result = $this->healthService->check($integration);
            Cache::put("integration.health.{$integration->id}", $result, 300);
            $results[$integration->id] = $result;
        }

        return response()->json(['success' => true, 'data' => $results]);
    }

    /**
     * PUT /api/admin/integrations/category/{category}/primary
     * Set the active primary provider for a category
     */
    public function setPrimary(Request $request, string $category)
    {
        $request->validate(['provider_id' => 'required|exists:integrations,id']);

        if (! array_key_exists($category, self::INTEGRATION_CATALOG)) {
            return response()->json(['success' => false, 'message' => 'Invalid category.'], 422);
        }

        DB::transaction(function () use ($category, $request) {
            Integration::where('category', $category)->update(['is_primary' => false]);
            Integration::where('id', $request->provider_id)->update(['is_primary' => true, 'is_active' => true]);
        });

        $provider = Integration::findOrFail($request->provider_id);

        $this->audit('integration_primary_changed', [
            'category'         => $category,
            'integration_id'   => $provider->id,
            'integration_name' => $provider->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$provider->name} is now the active provider for {$category}.",
        ]);
    }

    /**
     * GET /api/admin/integrations/billing/summary
     * Monthly per-provider cost and call count
     */
    public function billingSummary()
    {
        $summary = DB::table('integration_call_logs')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->select('integration_id', DB::raw('COUNT(*) as calls'), DB::raw('SUM(cost) as total_cost'))
            ->groupBy('integration_id')
            ->get()
            ->map(function ($row) {
                $integration = Integration::find($row->integration_id);
                $row->integration_name = $integration?->name;
                $row->category         = $integration?->category;

                return $row;
            });

        return response()->json(['success' => true, 'data' => $summary]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function decryptCredential(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return '';
        }
    }

    private function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 8) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 4) . str_repeat('•', strlen($value) - 8) . substr($value, -4);
    }

    private function getMonthlyCallCount(int $id): int
    {
        return DB::table('integration_call_logs')
            ->where('integration_id', $id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }

    private function getMonthlyCost(int $id): float
    {
        return (float) DB::table('integration_call_logs')
            ->where('integration_id', $id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('cost');
    }

    private function audit(string $action, array $payload = []): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'module'     => 'integrations',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'payload'    => $payload,
            'created_at' => now(),
        ]);
    }
}
