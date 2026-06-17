<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 13 — Screen 54: Feature Flags & A/B Tests
 * Gradual rollout per merchant cohort, A/B splits, kill-switch, audit trail
 */
class FeatureFlagController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:system.flags.view')
            ->only(['index', 'show', 'abTestResults', 'audit']);

        $this->middleware('permission:system.flags.create')
            ->only(['store']);

        $this->middleware('permission:system.flags.edit')
            ->only(['update']);

        $this->middleware('permission:system.flags.kill')
            ->only(['kill']);

        $this->middleware('permission:system.flags.abtest')
            ->only(['createAbTest']);
    }

    /**
     * GET /api/admin/feature-flags
     * List all flags with rollout status
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:on,off,partial',
            'search' => 'nullable|string|max:100',
        ]);

        $flags = FeatureFlag::query()
            ->when($request->status, fn($q) => $q->where('rollout_status', $request->status))
            ->when($request->search, fn($q) => $q->where('name', 'LIKE', "%{$request->search}%")
                ->orWhere('key', 'LIKE', "%{$request->search}%"))
            ->withCount('abTests')
            ->orderByDesc('updated_at')
            ->paginate(30);

        return response()->json(['success' => true, 'data' => $flags]);
    }

    /**
     * GET /api/admin/feature-flags/{key}
     * Single flag detail with cohort targeting and active A/B test
     */
    public function show(string $key)
    {
        $flag = $this->findFlag($key);
        $flag->load('activeAbTest');

        return response()->json(['success' => true, 'data' => $flag]);
    }

    /**
     * POST /api/admin/feature-flags
     * Create a new feature flag
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'key'         => 'required|string|max:100|unique:feature_flags,key|regex:/^[a-z0-9_]+$/',
            'description' => 'nullable|string|max:500',
            'type'        => 'required|in:boolean,percentage,cohort',
            'default_value' => 'required',
        ]);

        $flag = FeatureFlag::create([
            'name'           => $validated['name'],
            'key'            => $validated['key'],
            'description'    => $validated['description'],
            'type'           => $validated['type'],
            'default_value'  => json_encode($validated['default_value']),
            'rollout_status' => 'off',
            'rollout_percent'=> 0,
            'created_by'     => auth()->id(),
        ]);

        Cache::forget("feature_flag.{$flag->key}");
        activity()->log("Feature flag created: {$flag->key}");

        return response()->json(['success' => true, 'data' => $flag], 201);
    }

    /**
     * PUT /api/admin/feature-flags/{key}
     * Update flag — rollout status, percentage, cohort targeting
     */
    public function update(Request $request, string $key)
    {
        $flag = $this->findFlag($key);

        $validated = $request->validate([
            'rollout_status'  => 'sometimes|in:on,off,partial',
            'rollout_percent' => 'nullable|integer|min:0|max:100',
            'cohort_rules'    => 'nullable|array',
            'cohort_rules.merchant_tier'  => 'nullable|in:bronze,silver,gold',
            'cohort_rules.region'         => 'nullable|string',
            'cohort_rules.signup_after'   => 'nullable|date',
            'description'     => 'nullable|string|max:500',
        ]);

        $flag->update([
            'rollout_status'  => $validated['rollout_status']  ?? $flag->rollout_status,
            'rollout_percent' => $validated['rollout_percent'] ?? $flag->rollout_percent,
            'cohort_rules'    => isset($validated['cohort_rules']) ? json_encode($validated['cohort_rules']) : $flag->cohort_rules,
            'description'     => $validated['description'] ?? $flag->description,
            'updated_by'      => auth()->id(),
        ]);

        // Invalidate flag cache so all services pick up the change immediately
        Cache::forget("feature_flag.{$key}");
        flush_cache_tags(['feature_flags']);

        activity()->log("Feature flag updated: {$key} → status={$flag->rollout_status}, %={$flag->rollout_percent}");

        return response()->json(['success' => true, 'message' => "Flag '{$key}' updated.", 'data' => $flag->fresh()]);
    }

    /**
     * POST /api/admin/feature-flags/{key}/kill
     * Emergency kill-switch — immediately disable flag for everyone
     */
    public function kill(Request $request, string $key)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $flag = $this->findFlag($key);

        $flag->update([
            'rollout_status'  => 'off',
            'rollout_percent' => 0,
            'killed_at'       => now(),
            'killed_by'       => auth()->id(),
            'kill_reason'     => $request->reason,
        ]);

        Cache::forget("feature_flag.{$key}");
        flush_cache_tags(['feature_flags']);

        activity()->log("🚨 KILL SWITCH: Feature flag '{$key}' disabled. Reason: {$request->reason}");

        return response()->json([
            'success' => true,
            'message' => "Flag '{$key}' has been killed. All users are now on the default value.",
        ]);
    }

    /**
     * POST /api/admin/feature-flags/{key}/ab-test
     * Configure an A/B test on this flag
     */
    public function createAbTest(Request $request, string $key)
    {
        $flag = $this->findFlag($key);

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'variant_a_value'=> 'required',
            'variant_b_value'=> 'required',
            'traffic_split'  => 'required|integer|min:1|max:99',  // % going to variant B
            'metric'         => 'required|string|max:100',        // e.g. 'approval_rate', 'disbursal_volume'
            'start_at'       => 'required|date|after_or_equal:now',
            'end_at'         => 'required|date|after:start_at',
        ]);

        // End any active test for this flag
        DB::table('ab_tests')->where('flag_id', $flag->id)->where('status', 'active')->update(['status' => 'ended', 'ended_at' => now()]);

        $startAt = Carbon::parse($validated['start_at']);
        $status  = $startAt->lte(now()) ? 'active' : 'scheduled';

        $test = DB::table('ab_tests')->insertGetId([
            'flag_id'         => $flag->id,
            'name'            => $validated['name'],
            'variant_a_value' => json_encode($validated['variant_a_value']),
            'variant_b_value' => json_encode($validated['variant_b_value']),
            'traffic_split'   => $validated['traffic_split'],
            'metric'          => $validated['metric'],
            'start_at'        => $validated['start_at'],
            'end_at'          => $validated['end_at'],
            'status'          => $status,
            'created_by'      => auth()->id(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        activity()->log("A/B test created for flag '{$key}': {$validated['name']}");

        return response()->json([
            'success'    => true,
            'ab_test_id' => $test,
            'status'     => $status,
            'message'    => $status === 'active'
                ? 'A/B test is now active.'
                : 'A/B test scheduled.',
        ], 201);
    }

    /**
     * GET /api/admin/feature-flags/{key}/ab-test/results
     * Live A/B test results with statistical significance
     */
    public function abTestResults(string $key)
    {
        $flag = $this->findFlag($key);

        $this->activateDueAbTests($flag->id);

        $test = DB::table('ab_tests')
            ->where('flag_id', $flag->id)
            ->where('status', 'active')
            ->where('start_at', '<=', now())
            ->where('end_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$test) {
            return response()->json(['success' => false, 'message' => 'No active A/B test for this flag.'], 404);
        }

        $results = DB::table('ab_test_events')
            ->where('test_id', $test->id)
            ->select('variant', DB::raw('COUNT(*) as exposures'), DB::raw('SUM(CASE WHEN converted = true THEN 1 ELSE 0 END) as conversions'))
            ->groupBy('variant')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'test'       => $test,
                'results'    => $results,
                'is_significant' => $this->isStatisticallySignificant($results),
            ],
        ]);
    }

    /**
     * GET /api/admin/feature-flags/{key}/audit
     * Audit trail of all changes to this flag
     */
    public function audit(string $key)
    {
        $flag = $this->findFlag($key);
        $key = $flag->key;

        $logs = DB::table('audit_logs')
            ->where('action', 'activity_log')
            ->where('payload->message', 'like', '%' . $key . '%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'data' => $logs]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function findFlag(string $key): FeatureFlag
    {
        return FeatureFlag::where('key', trim($key))->firstOrFail();
    }

    private function isStatisticallySignificant($results): bool
    {
        // Simplified: needs at least 100 exposures per variant to be considered significant
        $variants = collect($results);
        return $variants->every(fn($v) => $v->exposures >= 100);
    }

    private function activateDueAbTests(int $flagId): void
    {
        DB::table('ab_tests')
            ->where('flag_id', $flagId)
            ->where('status', 'scheduled')
            ->where('start_at', '<=', now())
            ->where('end_at', '>', now())
            ->update(['status' => 'active', 'updated_at' => now()]);
    }
}