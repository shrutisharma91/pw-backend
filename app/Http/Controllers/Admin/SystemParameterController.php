<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\ToggleDebugLoggingRequest;
use App\Http\Requests\System\UpdateSystemParametersRequest;
use App\Http\Resources\DebugLoggingStatusResource;
use App\Http\Resources\SystemParametersResource;
use App\Services\SystemParameterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 13 — Screen 55: System Parameters & Settings
 * Platform-wide config: rates, fees, OTP expiry, SLAs, maintenance mode, audit log
 */
class SystemParameterController extends Controller
{
    public function __construct(private SystemParameterService $systemParameterService)
    {
        $this->middleware('permission:system.parameters.view')
            ->only(['index', 'show', 'audit', 'debugLoggingStatus']);

        $this->middleware('permission:system.parameters.edit')
            ->only(['update', 'toggleDebugLogging', 'resetToDefaults']);

        $this->middleware('permission:system.maintenance.toggle')
            ->only(['toggleMaintenance']);
    }

    /**
     * GET /api/admin/system/parameters
     * All system parameters grouped by category
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => new SystemParametersResource($this->systemParameterService->groupedParameters()),
        ]);
    }

    /**
     * GET /api/admin/system/parameters/{key}
     * Single parameter value
     */
    public function show(string $key)
    {
        $detail = $this->systemParameterService->parameterDetail($key);

        if ($detail === null) {
            return response()->json(['success' => false, 'message' => 'Unknown parameter key.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $detail,
        ]);
    }

    /**
     * PUT /api/admin/system/parameters
     * Batch update multiple parameters in one atomic transaction
     */
    public function update(UpdateSystemParametersRequest $request)
    {
        $count = $this->systemParameterService->updateParameters(
            $request->validated('parameters'),
            (int) auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => "{$count} parameter(s) updated.",
        ]);
    }

    /**
     * GET /api/admin/system/parameters/debug-logging
     * Current debug logging status
     */
    public function debugLoggingStatus()
    {
        return response()->json([
            'success' => true,
            'data'    => new DebugLoggingStatusResource($this->systemParameterService->debugLoggingStatus()),
        ]);
    }

    /**
     * PUT /api/admin/system/parameters/debug-logging
     * Enable or disable debug logging
     */
    public function toggleDebugLogging(ToggleDebugLoggingRequest $request)
    {
        $status = $this->systemParameterService->setDebugLogging(
            $request->boolean('enabled'),
            (int) auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => $request->boolean('enabled')
                ? 'Debug logging enabled.'
                : 'Debug logging disabled.',
            'data'    => new DebugLoggingStatusResource($status),
        ]);
    }

    /**
     * POST /api/admin/system/parameters/reset
     * Restore all configurable parameters to default values
     */
    public function resetToDefaults()
    {
        $grouped = $this->systemParameterService->resetToDefaults((int) auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'System parameters reset to default values.',
            'data'    => new SystemParametersResource($grouped),
        ]);
    }

    /**
     * POST /api/admin/system/maintenance
     * Toggle maintenance mode with custom banner
     */
    public function toggleMaintenance(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'banner'  => 'nullable|string|max:500',
            'ends_at' => 'nullable|date|after:now',
        ]);

        DB::transaction(function () use ($request) {
            $userId = (int) auth()->id();
            $this->systemParameterService->upsert('maintenance_mode', (int) $request->enabled, $userId);
            $this->systemParameterService->upsert('maintenance_banner', $request->banner ?? '', $userId);

            if ($request->ends_at) {
                $this->systemParameterService->upsert('maintenance_ends_at', $request->ends_at, $userId);
            }
        });

        flush_cache_tags(['system_parameters']);

        $status = $request->enabled ? '🚨 ENABLED' : 'DISABLED';
        activity()->log("Maintenance mode {$status}" . ($request->banner ? ": {$request->banner}" : ''));

        return response()->json([
            'success' => true,
            'message' => $request->enabled
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
            ->where(function ($query) {
                $query->where('payload->message', 'like', 'System parameter updated%')
                    ->orWhere('payload->message', 'System parameters reset to defaults');
            })
            ->when($request->key, fn ($q) => $q->where('payload->key', $request->key))
            ->when($request->start_date, fn ($q) => $q->whereDate('created_at', '>=', $request->start_date))
            ->when($request->end_date, fn ($q) => $q->whereDate('created_at', '<=', $request->end_date))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
