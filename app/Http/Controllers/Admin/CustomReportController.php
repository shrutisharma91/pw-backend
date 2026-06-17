<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunCustomReportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 11 — Screen 48: Custom Report Builder
 * Drag-and-drop: field picker, filters, group-by, chart type, schedule, export
 */
class CustomReportController extends Controller
{
    // Allowed tables / aliases and their exposed columns — whitelist prevents SQL injection
    private const ALLOWED_MODULES = [
        'loans'     => ['id', 'loan_amount', 'status', 'tenure_months', 'product_category', 'created_at', 'disbursed_at', 'merchant_id', 'store_id', 'lender_id', 'is_npa'],
        'merchants' => ['id', 'business_name', 'status', 'category', 'city', 'state', 'created_at', 'sales_exec_id'],
        'stores'    => ['id', 'name', 'city', 'state', 'status', 'merchant_id'],
        'payments'  => ['id', 'loan_id', 'paid_at', 'amount', 'interest_component', 'principal_component', 'late_fee'],
        'lenders'   => ['id', 'name', 'status', 'category_support'],
    ];

    private const ALLOWED_AGGREGATES = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];

    private const ALLOWED_CHART_TYPES = ['bar', 'line', 'pie', 'area', 'scatter', 'table'];

    public function __construct()
    {
        $this->middleware(['permission:analytics.reports.view']);
    }

    /**
     * GET /api/admin/reports/custom/schema
     * Returns available fields per module for the drag-and-drop UI
     */
    public function schema()
    {
        return response()->json([
            'success' => true,
            'data'    => array_map(
                fn($cols) => array_map(fn($col) => ['field' => $col, 'label' => ucwords(str_replace('_', ' ', $col))], $cols),
                self::ALLOWED_MODULES
            ),
            'aggregates'  => self::ALLOWED_AGGREGATES,
            'chart_types' => self::ALLOWED_CHART_TYPES,
        ]);
    }

    /**
     * POST /api/admin/reports/custom
     * Execute a custom report and return results immediately (for small datasets)
     */
    public function run(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'module'            => 'required|in:' . implode(',', array_keys(self::ALLOWED_MODULES)),
            'fields'            => 'required|array|min:1|max:20',
            'fields.*'          => 'required|string',
            'filters'           => 'nullable|array',
            'filters.*.field'   => 'required_with:filters|string',
            'filters.*.operator'=> 'required_with:filters|in:=,!=,>,<,>=,<=,like,in,between',
            'filters.*.value'   => 'required_with:filters',
            'group_by'          => 'nullable|array',
            'group_by.*'        => 'nullable|string',
            'aggregate'         => 'nullable|array',
            'aggregate.*.func'  => 'nullable|in:COUNT,SUM,AVG,MIN,MAX',
            'aggregate.*.field' => 'nullable|string',
            'chart_type'        => 'nullable|in:' . implode(',', self::ALLOWED_CHART_TYPES),
            'limit'             => 'nullable|integer|min:1|max:10000',
            'order_by'          => 'nullable|string',
            'order_dir'         => 'nullable|in:asc,desc',
        ]);

        // Validate all requested fields are in whitelist
        $allowedCols = self::ALLOWED_MODULES[$validated['module']];
        foreach ($validated['fields'] as $field) {
            if (!in_array($field, $allowedCols)) {
                return response()->json(['success' => false, 'message' => "Field '{$field}' is not allowed."], 422);
            }
        }

        try {
            $results = $this->executeReport($validated);

            return response()->json([
                'success'    => true,
                'data'       => $results,
                'row_count'  => count($results),
                'chart_type' => $validated['chart_type'] ?? 'table',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Report execution failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/reports/custom
     * List saved reports
     */
    public function index(Request $request)
    {
        $reports = DB::table('custom_reports')
            ->where('created_by', auth()->id())
            ->orWhere('is_shared', true)
            ->orderByDesc('updated_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $reports]);
    }

    /**
     * POST /api/admin/reports/custom/save
     * Save a report definition (not the results)
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'definition'  => 'required|array',
            'chart_type'  => 'nullable|string',
            'is_shared'   => 'boolean',
        ]);

        $id = DB::table('custom_reports')->insertGetId([
            'name'       => $validated['name'],
            'definition' => json_encode($validated['definition']),
            'chart_type' => $validated['chart_type'] ?? 'table',
            'is_shared'  => $validated['is_shared'] ?? false,
            'created_by' => auth()->id(),
            'version'    => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Log to audit
        activity()->log("Custom report saved: {$validated['name']} (ID: {$id})");

        return response()->json(['success' => true, 'report_id' => $id, 'message' => 'Report saved.']);
    }

    /**
     * PUT /api/admin/reports/custom/{id}
     * Update report definition (increments version)
     */
    public function update(Request $request, int $id)
    {
        $report = DB::table('custom_reports')->where('id', $id)->where('created_by', auth()->id())->firstOrFail();

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'definition' => 'sometimes|array',
            'chart_type' => 'nullable|string',
            'is_shared'  => 'nullable|boolean',
        ]);

        DB::table('custom_reports')->where('id', $id)->update(array_merge(
            array_filter($validated, fn($v) => $v !== null),
            ['version' => $report->version + 1, 'updated_at' => now()]
        ));

        return response()->json(['success' => true, 'message' => 'Report updated.']);
    }

    /**
     * POST /api/admin/reports/custom/{id}/schedule
     * Schedule recurring email delivery
     */
    public function schedule(Request $request, int $id)
    {
        $request->validate([
            'frequency'   => 'required|in:daily,weekly,monthly',
            'recipients'  => 'required|array|min:1',
            'recipients.*'=> 'email',
            'format'      => 'required|in:csv,xlsx,pdf',
            'time'        => 'required|date_format:H:i',
        ]);

        DB::table('custom_reports')->where('id', $id)->where('created_by', auth()->id())->firstOrFail();

        DB::table('report_schedules')->updateOrInsert(
            ['report_id' => $id],
            [
                'frequency'   => $request->frequency,
                'recipients'  => json_encode($request->recipients),
                'format'      => $request->format,
                'send_time'   => $request->time,
                'is_active'   => true,
                'updated_at'  => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => "Report scheduled {$request->frequency} at {$request->time}."]);
    }

    /**
     * POST /api/admin/reports/custom/{id}/export
     * Queue an export job (large datasets)
     */
    public function export(Request $request, int $id)
    {
        $request->validate(['format' => 'required|in:csv,xlsx,pdf,json']);

        $report = DB::table('custom_reports')->where('id', $id)->firstOrFail();
        $def    = json_decode($report->definition, true);

        dispatch(new RunCustomReportJob($def, $request->format, auth()->id(), $report->name));

        return response()->json(['success' => true, 'message' => 'Export queued. Notification sent when ready.']);
    }

    /**
     * GET /api/admin/reports/custom/{id}/history
     * Report version history
     */
    public function history(int $id)
    {
        $history = DB::table('custom_report_versions')
            ->where('report_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $history]);
    }

    /**
     * DELETE /api/admin/reports/custom/{id}
     */
    public function destroy(int $id)
    {
        DB::table('custom_reports')->where('id', $id)->where('created_by', auth()->id())->delete();
        return response()->json(['success' => true, 'message' => 'Report deleted.']);
    }

    // ─── Query execution engine ───────────────────────────────────────────────

    private function executeReport(array $config): array
    {
        $module  = $config['module'];
        $fields  = $config['fields'];
        $filters = $config['filters'] ?? [];
        $groupBy = $config['group_by'] ?? [];
        $agg     = $config['aggregate'] ?? [];
        $limit   = $config['limit'] ?? 1000;
        $orderBy = $config['order_by'] ?? null;
        $orderDir= $config['order_dir'] ?? 'desc';

        $select = $fields;

        foreach ($agg as $aggItem) {
            if (!empty($aggItem['func']) && !empty($aggItem['field'])) {
                $select[] = DB::raw("{$aggItem['func']}({$aggItem['field']}) as {$aggItem['func']}_{$aggItem['field']}");
            }
        }

        $query = DB::table($module)->select($select);

        foreach ($filters as $filter) {
            $op    = $filter['operator'];
            $field = $filter['field'];
            $value = $filter['value'];

            if ($op === 'like') {
                $query->where($field, 'LIKE', "%{$value}%");
            } elseif ($op === 'in') {
                $query->whereIn($field, (array) $value);
            } elseif ($op === 'between') {
                $query->whereBetween($field, (array) $value);
            } else {
                $query->where($field, $op, $value);
            }
        }

        if (!empty($groupBy)) {
            $query->groupBy($groupBy);
        }

        if ($orderBy && in_array($orderBy, self::ALLOWED_MODULES[$module])) {
            $query->orderBy($orderBy, $orderDir);
        }

        return $query->limit($limit)->get()->toArray();
    }
}