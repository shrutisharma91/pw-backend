<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Phase 13 — Screen 55: System Parameters & Settings
 * Platform-wide config: rates, fees, OTP expiry, SLAs, maintenance mode, audit log
 */
class SystemParameterController extends Controller
{
    // All allowed parameter keys with types and allowed updaters
    private const PARAMETER_SCHEMA = [
        // Rates & Fees
        'default_interest_rate'        => ['type' => 'float',   'label' => 'Default Interest Rate (%)',       'group' => 'rates'],
        'default_processing_fee'       => ['type' => 'float',   'label' => 'Default Processing Fee (%)',      'group' => 'rates'],
        'default_late_payment_fee'     => ['type' => 'float',   'label' => 'Default Late Payment Fee (₹)',    'group' => 'rates'],
        'default_bounce_charge'        => ['type' => 'float',   'label' => 'Bounce Charge (₹)',               'group' => 'rates'],
        'max_merchant_discount'        => ['type' => 'float',   'label' => 'Max Merchant Discount (%)',       'group' => 'rates'],

        // Auth & Security
        'otp_expiry_minutes'           => ['type' => 'int',     'label' => 'OTP Expiry (minutes)',            'group' => 'security'],
        'otp_max_retries'              => ['type' => 'int',     'label' => 'OTP Max Retries',                 'group' => 'security'],
        'login_lockout_attempts'       => ['type' => 'int',     'label' => 'Login Lockout Attempts',          'group' => 'security'],
        'login_lockout_minutes'        => ['type' => 'int',     'label' => 'Login Lockout Duration (min)',    'group' => 'security'],
        'session_timeout_minutes'      => ['type' => 'int',     'label' => 'Session Timeout (minutes)',       'group' => 'security'],
        'password_expiry_days'         => ['type' => 'int',     'label' => 'Password Expiry (days)',          'group' => 'security'],
        'mfa_trusted_device_days'      => ['type' => 'int',     'label' => 'MFA Trusted Device (days)',       'group' => 'security'],
        'reset_link_expiry_minutes'    => ['type' => 'int',     'label' => 'Password Reset Link Expiry (min)','group' => 'security'],

        // SLAs
        'kyc_review_sla_hours'         => ['type' => 'int',     'label' => 'KYC Review SLA (hours)',          'group' => 'sla'],
        'loan_approval_sla_minutes'    => ['type' => 'int',     'label' => 'Loan Approval SLA (minutes)',     'group' => 'sla'],
        'disbursal_sla_hours'          => ['type' => 'int',     'label' => 'Disbursal SLA (hours)',           'group' => 'sla'],
        'ticket_first_response_sla_hours' => ['type' => 'int', 'label' => 'Ticket First Response SLA (hrs)', 'group' => 'sla'],
        'offer_approval_sla_hours'     => ['type' => 'int',     'label' => 'Offer Approval SLA (hours)',      'group' => 'sla'],

        // Platform Limits
        'max_loan_amount'              => ['type' => 'int',     'label' => 'Max Loan Amount (₹)',             'group' => 'limits'],
        'min_loan_amount'              => ['type' => 'int',     'label' => 'Min Loan Amount (₹)',             'group' => 'limits'],
        'max_emi_tenure_months'        => ['type' => 'int',     'label' => 'Max EMI Tenure (months)',         'group' => 'limits'],
        'manual_override_threshold'    => ['type' => 'int',     'label' => 'Manual Override Dual-Auth Threshold (₹)', 'group' => 'limits'],
        'auto_approval_offer_threshold'=> ['type' => 'int',     'label' => 'Auto-Approve Offer Threshold (₹)','group' => 'limits'],

        // Maintenance
        'maintenance_mode'             => ['type' => 'bool',    'label' => 'Maintenance Mode',                'group' => 'platform'],
        'maintenance_banner'           => ['type' => 'string',  'label' => 'Maintenance Banner Message',      'group' => 'platform'],
        'maintenance_ends_at'          => ['type' => 'datetime','label' => 'Maintenance Ends At',             'group' => 'platform'],
        'platform_name'                => ['type' => 'string',  'label' => 'Platform Name',                   'group' => 'platform'],
        'support_email'                => ['type' => 'string',  'label' => 'Support Email',                   'group' => 'platform'],
        'support_phone'                => ['type' => 'string',  'label' => 'Support Phone',                   'group' => 'platform'],
    ];

    public function __construct()
    {
        $this->middleware('permission:system.parameters.view')
            ->only(['index', 'show', 'audit']);

        $this->middleware('permission:system.parameters.edit')
            ->only(['update']);

        $this->middleware('permission:system.maintenance.toggle')
            ->only(['toggleMaintenance']);
    }

    /**
     * GET /api/admin/system/parameters
     * All system parameters grouped by category
     */
    public function index()
    {
        $stored = DB::table('system_parameters')
            ->orderBy('key')
            ->get()
            ->keyBy('key');

        $grouped = collect(self::PARAMETER_SCHEMA)
            ->groupBy(fn($schema) => $schema['group'])
            ->map(function ($params, $group) use ($stored) {
                return collect($params)->map(function ($schema, $key) use ($stored) {
                    $row = $stored->get($key);
                    return [
                        'key'         => $key,
                        'label'       => $schema['label'],
                        'type'        => $schema['type'],
                        'value'       => $row ? $this->castValue($row->value, $schema['type']) : null,
                        'updated_at'  => $row?->updated_at,
                        'updated_by'  => $row?->updated_by,
                    ];
                })->values();
            });

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    /**
     * GET /api/admin/system/parameters/{key}
     * Single parameter value
     */
    public function show(string $key)
    {
        if (!array_key_exists($key, self::PARAMETER_SCHEMA)) {
            return response()->json(['success' => false, 'message' => 'Unknown parameter key.'], 404);
        }

        $row = DB::table('system_parameters')->where('key', $key)->first();

        return response()->json([
            'success' => true,
            'data'    => array_merge(self::PARAMETER_SCHEMA[$key], [
                'key'   => $key,
                'value' => $row ? $this->castValue($row->value, self::PARAMETER_SCHEMA[$key]['type']) : null,
            ]),
        ]);
    }

    /**
     * PUT /api/admin/system/parameters
     * Batch update multiple parameters in one atomic transaction
     */
    public function update(Request $request)
    {
        $request->validate([
            'parameters'        => 'required|array|min:1',
            'parameters.*.key'  => 'required|string',
            'parameters.*.value'=> 'required',
        ]);

        $unknownKeys = collect($request->parameters)
            ->pluck('key')
            ->filter(fn($k) => !array_key_exists($k, self::PARAMETER_SCHEMA));

        if ($unknownKeys->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown parameter keys: ' . $unknownKeys->implode(', '),
            ], 422);
        }

        DB::transaction(function () use ($request) {
            foreach ($request->parameters as $param) {
                $key    = $param['key'];
                $schema = self::PARAMETER_SCHEMA[$key];
                $value  = $this->sanitizeValue($param['value'], $schema['type']);

                // Capture old value for audit
                $oldValue = DB::table('system_parameters')->where('key', $key)->value('value');

                DB::table('system_parameters')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => auth()->id(), 'updated_at' => now()]
                );

                // Write to audit trail
                activity()->withProperties(['key' => $key, 'old_value' => $oldValue, 'new_value' => $value])
                    ->log("System parameter updated: {$key}");
            }
        });

        // Invalidate all cached parameter values
        flush_cache_tags(['system_parameters']);

        return response()->json(['success' => true, 'message' => count($request->parameters) . ' parameter(s) updated.']);
    }

    /**
     * POST /api/admin/system/maintenance
     * Toggle maintenance mode with custom banner
     */
    public function toggleMaintenance(Request $request)
    {
        $request->validate([
            'enabled'    => 'required|boolean',
            'banner'     => 'nullable|string|max:500',
            'ends_at'    => 'nullable|date|after:now',
        ]);

        DB::transaction(function () use ($request) {
            $this->upsertParam('maintenance_mode', (int) $request->enabled);
            $this->upsertParam('maintenance_banner', $request->banner ?? '');
            if ($request->ends_at) {
                $this->upsertParam('maintenance_ends_at', $request->ends_at);
            }
        });

        flush_cache_tags(['system_parameters']);

        $status = $request->enabled ? '🚨 ENABLED' : 'DISABLED';
        activity()->log("Maintenance mode {$status}" . ($request->banner ? ": {$request->banner}" : ''));

        return response()->json([
            'success'  => true,
            'message'  => $request->enabled
                ? 'Maintenance mode is ON. The platform banner is live.'
                : 'Maintenance mode is OFF. Platform is accessible.',
        ]);
    }

    /**
     * GET /api/admin/system/parameters/audit
     * Full audit log of parameter changes
     */
    public function audit(Request $request)
    {
        $request->validate([
            'key'        => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        $logs = DB::table('audit_logs')
            ->where('action', 'activity_log')
            ->where('payload->message', 'like', 'System parameter updated%')
            ->when($request->key, fn ($q) => $q->where('payload->key', $request->key))
            ->when($request->start_date, fn ($q) => $q->whereDate('created_at', '>=', $request->start_date))
            ->when($request->end_date, fn ($q) => $q->whereDate('created_at', '<=', $request->end_date))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function castValue(string $raw, string $type): mixed
    {
        return match ($type) {
            'int'      => (int) $raw,
            'float'    => (float) $raw,
            'bool'     => (bool) (int) $raw,
            'datetime' => $raw,
            default    => $raw,
        };
    }

    private function sanitizeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'int'   => (string) (int) $value,
            'float' => (string) (float) $value,
            'bool'  => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private function upsertParam(string $key, mixed $value): void
    {
        DB::table('system_parameters')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $value, 'updated_by' => auth()->id(), 'updated_at' => now()]
        );
    }
}