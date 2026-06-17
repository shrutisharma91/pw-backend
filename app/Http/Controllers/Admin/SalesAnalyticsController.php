<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 11 — Screen 47: Sales & Region Analytics
 * Sales exec leaderboard, region heatmap, pipeline funnel, underperforming alerts
 */
class SalesAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:analytics.sales.view']);
    }

    private function merchantStatusCount($merchants, string $status): int
    {
        return $merchants->filter(fn ($m) => strtolower((string) $m->status) === strtolower($status))->count();
    }

    private function merchantStatusInCount($merchants, array $statuses): int
    {
        $normalized = array_map('strtolower', $statuses);

        return $merchants->filter(fn ($m) => in_array(strtolower((string) $m->status), $normalized, true))->count();
    }

    /**
     * GET /api/admin/analytics/sales
     * Full sales analytics payload
     */
    public function index(Request $request)
    {
        $request->validate([
            'period'  => 'nullable|in:7d,30d,90d,1y',
            'region'  => 'nullable|string|max:100',
            'exec_id' => 'nullable|exists:users,id',
        ]);

        $period = $request->period ?? '30d';
        [$start, $end] = $this->resolveDateRange($period);

        $cacheKey = "analytics.sales.{$period}.{$request->region}.{$request->exec_id}";

        $data = Cache::remember($cacheKey, 300, function () use ($start, $end, $request) {
            return [
                'leaderboard'           => $this->getLeaderboard($start, $end),
                'region_heatmap'        => $this->getRegionHeatmap($start, $end),
                'pipeline_funnel'       => $this->getPipelineFunnel($start, $end, $request->exec_id),
                'underperforming_alerts'=> $this->getUnderperformingRegions($start, $end),
                'drill_down'            => $request->region ? $this->getDrillDown($start, $end, $request->region) : null,
                'exec_detail'           => $request->exec_id ? $this->getExecDetail($start, $end, $request->exec_id) : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/admin/analytics/sales/region/{state}/stores
     * Drill down from region → store → loan list
     */
    public function regionStores(Request $request, string $state)
    {
        $stores = DB::table('stores')
            ->join('merchants', 'stores.merchant_id', '=', 'merchants.id')
            ->where('merchants.region', $state)
            ->select(
                'stores.id',
                'stores.name',
                'stores.address',
                'merchants.business_name',
                'merchants.region',
                DB::raw("(SELECT COUNT(*) FROM loans WHERE store_id = stores.id AND status = 'disbursed') as disbursals"),
                DB::raw("(SELECT SUM(loan_amount) FROM loans WHERE store_id = stores.id AND status = 'disbursed') as disbursal_volume")
            )
            ->orderByDesc('disbursals')
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $stores]);
    }

    /**
     * GET /api/admin/analytics/sales/exec/{id}/pipeline
     * Individual exec pipeline view
     */
    public function execPipeline(int $id)
    {
        $exec      = DB::table('users')->where('id', $id)->where('role', 'sales_exec')->firstOrFail();
        $merchants = Merchant::where('sales_exec_id', $id)->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'exec'         => $exec,
                'pipeline'     => [
                    'leads_generated'  => $merchants->count(),
                    'submitted'        => $this->merchantStatusCount($merchants, 'submitted'),
                    'under_review'     => $this->merchantStatusCount($merchants, 'under review'),
                    'approved'         => $this->merchantStatusCount($merchants, 'approved'),
                    'rejected'         => $this->merchantStatusCount($merchants, 'rejected'),
                    'conversion_rate'  => $this->execConversionRate($merchants),
                ],
                'merchants'    => $merchants->sortByDesc('created_at')->take(20)->values(),
                'monthly_trend'=> $this->execMonthlyTrend($id),
            ],
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getLeaderboard(string $start, string $end): array
    {
        return DB::table('users')
            ->where('users.role', 'sales_exec')
            ->leftJoin('merchants', function ($join) use ($start, $end) {
                $join->on('merchants.sales_exec_id', '=', 'users.id')
                    ->whereBetween('merchants.created_at', [$start, $end]);
            })
            ->leftJoin('loans', function ($join) use ($start, $end) {
                $join->on('loans.merchant_id', '=', 'merchants.id')
                    ->where('loans.status', 'disbursed')
                    ->whereBetween('loans.disbursed_at', [$start, $end]);
            })
            ->select(
                'users.id',
                'users.name',
                'users.email',
                DB::raw("(SELECT merchants.region FROM merchants WHERE merchants.sales_exec_id = users.id ORDER BY merchants.created_at DESC LIMIT 1) as primary_region"),
                DB::raw('COUNT(DISTINCT merchants.id) as merchants_onboarded'),
                DB::raw("COUNT(DISTINCT CASE WHEN LOWER(merchants.status) = 'approved' THEN merchants.id END) as merchants_approved"),
                DB::raw('COUNT(DISTINCT loans.id) as total_disbursals'),
                DB::raw('SUM(loans.loan_amount) as total_volume'),
                DB::raw("ROUND(COUNT(DISTINCT CASE WHEN LOWER(merchants.status) = 'approved' THEN merchants.id END) / NULLIF(COUNT(DISTINCT merchants.id), 0) * 100, 1) as conversion_rate")
            )
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('total_volume')
            ->limit(50)
            ->get()
            ->toArray();
    }

    private function getRegionHeatmap(string $start, string $end): array
    {
        return DB::table('loans')
            ->join('merchants', 'loans.merchant_id', '=', 'merchants.id')
            ->leftJoin('stores', 'loans.store_id', '=', 'stores.id')
            ->where('loans.status', 'disbursed')
            ->whereBetween('loans.disbursed_at', [$start, $end])
            ->whereNotNull('merchants.region')
            ->select(
                'merchants.region',
                DB::raw('COUNT(DISTINCT merchants.id) as merchants'),
                DB::raw('COUNT(DISTINCT stores.id) as stores'),
                DB::raw('COUNT(loans.id) as disbursals'),
                DB::raw('SUM(loans.loan_amount) as disbursal_volume')
            )
            ->groupBy('merchants.region')
            ->orderByDesc('disbursal_volume')
            ->get()
            ->toArray();
    }

    private function getPipelineFunnel(string $start, string $end, ?int $execId): array
    {
        $query = Merchant::whereBetween('created_at', [$start, $end]);

        if ($execId) {
            $query->where('sales_exec_id', $execId);
        }

        $merchants = $query->get();

        return [
            'leads_generated' => $merchants->count(),
            'submitted'       => $this->merchantStatusInCount($merchants, ['submitted', 'under review', 'approved', 'rejected']),
            'under_review'    => $this->merchantStatusInCount($merchants, ['under review', 'approved', 'rejected']),
            'approved'        => $this->merchantStatusCount($merchants, 'approved'),
            'first_disbursal' => DB::table('loans')
                ->join('merchants', 'loans.merchant_id', '=', 'merchants.id')
                ->where('loans.status', 'disbursed')
                ->whereBetween('loans.disbursed_at', [$start, $end])
                ->when($execId, fn ($q) => $q->where('merchants.sales_exec_id', $execId))
                ->distinct('loans.merchant_id')
                ->count('loans.merchant_id'),
        ];
    }

    private function getUnderperformingRegions(string $start, string $end): array
    {
        $avgVolume = DB::table('loans')
            ->join('merchants', 'loans.merchant_id', '=', 'merchants.id')
            ->where('loans.status', 'disbursed')
            ->whereBetween('loans.disbursed_at', [$start, $end])
            ->selectRaw('AVG(loans.loan_amount) as avg')
            ->value('avg') ?? 0;

        return DB::table('loans')
            ->join('merchants', 'loans.merchant_id', '=', 'merchants.id')
            ->where('loans.status', 'disbursed')
            ->whereBetween('loans.disbursed_at', [$start, $end])
            ->whereNotNull('merchants.region')
            ->select('merchants.region', DB::raw('SUM(loans.loan_amount) as volume'), DB::raw('COUNT(loans.id) as count'))
            ->groupBy('merchants.region')
            ->havingRaw('SUM(loans.loan_amount) < ?', [$avgVolume * 0.5])
            ->orderBy('volume')
            ->get()
            ->map(fn ($r) => array_merge((array) $r, ['recommended_action' => 'Schedule regional sales review and training.']))
            ->toArray();
    }

    private function getDrillDown(string $start, string $end, string $region): array
    {
        return DB::table('stores')
            ->join('merchants', 'stores.merchant_id', '=', 'merchants.id')
            ->join('loans', 'loans.store_id', '=', 'stores.id')
            ->where('merchants.region', $region)
            ->where('loans.status', 'disbursed')
            ->whereBetween('loans.disbursed_at', [$start, $end])
            ->select(
                'stores.id',
                'stores.name',
                'stores.address',
                'merchants.business_name',
                DB::raw('COUNT(loans.id) as disbursals'),
                DB::raw('SUM(loans.loan_amount) as volume')
            )
            ->groupBy('stores.id', 'stores.name', 'stores.address', 'merchants.business_name')
            ->orderByDesc('volume')
            ->limit(20)
            ->get()
            ->toArray();
    }

    private function getExecDetail(string $start, string $end, int $execId): array
    {
        $exec = DB::table('users')->where('id', $execId)->first(['id', 'name', 'email', 'role', 'mobile']);

        if (!$exec) {
            return [];
        }

        $merchants = Merchant::where('sales_exec_id', $execId)->whereBetween('created_at', [$start, $end])->get();

        return [
            'exec'            => $exec,
            'merchants_total' => $merchants->count(),
            'approved'        => $this->merchantStatusCount($merchants, 'approved'),
            'conversion_rate' => $this->execConversionRate($merchants),
            'monthly_trend'   => $this->execMonthlyTrend($execId),
        ];
    }

    private function execConversionRate($merchants): float
    {
        $total    = $merchants->count();
        $approved = $this->merchantStatusCount($merchants, 'approved');
        return $total > 0 ? round($approved / $total * 100, 1) : 0;
    }

    private function execMonthlyTrend(int $execId): array
    {
        return DB::table('merchants')
            ->where('sales_exec_id', $execId)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as leads, SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) as approved")
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get()
            ->toArray();
    }

    private function resolveDateRange(string $period): array
    {
        return match ($period) {
            '7d'  => [now()->subDays(7)->toDateString(),  now()->toDateString()],
            '30d' => [now()->subDays(30)->toDateString(), now()->toDateString()],
            '90d' => [now()->subDays(90)->toDateString(), now()->toDateString()],
            '1y'  => [now()->subYear()->toDateString(),   now()->toDateString()],
            default => [now()->subDays(30)->toDateString(), now()->toDateString()],
        };
    }
}