<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Merchant;
use App\Models\Lender;
use App\Models\Store;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Phase 11 — Screen 45: Business Analytics Dashboard
 * Executive KPIs: disbursals, revenue, growth, cohort retention, funnel
 */
class BusinessAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:analytics.business.view']);
    }

    /**
     * GET /api/admin/analytics/business
     * Master business KPI payload with optional date range
     */
    public function index(Request $request)
    {
        $request->validate([
            'period'     => 'nullable|in:7d,30d,90d,1y,custom',
            'start_date' => 'nullable|required_if:period,custom|date',
            'end_date'   => 'nullable|required_if:period,custom|date|after_or_equal:start_date',
        ]);

        [$startDate, $endDate] = $this->resolveDateRange($request->period ?? '30d', $request);

        $cacheKey = "analytics.business.{$request->period}.{$startDate}.{$endDate}";

        $data = Cache::remember($cacheKey, 300, function () use ($startDate, $endDate) {
            return [
                'summary'          => $this->getSummaryKpis($startDate, $endDate),
                'disbursal_trend'  => $this->getDisbursalTrend($startDate, $endDate),
                'revenue_waterfall'=> $this->getRevenueWaterfall($startDate, $endDate),
                'funnel'           => $this->getApplicationFunnel($startDate, $endDate),
                'cohort_retention' => $this->getCohortRetention(),
                'top_merchants'    => $this->getTopMerchants($startDate, $endDate),
                'yoy_comparison'   => $this->getYoYComparison($endDate),
                'mom_comparison'   => $this->getMoMComparison($endDate),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'period'     => $request->period ?? '30d',
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'generated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * GET /api/admin/analytics/business/snapshot
     * Save a board-ready snapshot to PDF-exportable format
     */
    public function saveSnapshot(Request $request)
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'period' => 'required|in:7d,30d,90d,1y',
        ]);

        $snapshot = DB::table('analytics_snapshots')->insertGetId([
            'title'      => $request->title,
            'period'     => $request->period,
            'created_by' => auth()->id(),
            'payload'    => json_encode($this->index($request)->getData(true)['data']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success'     => true,
            'snapshot_id' => $snapshot,
            'message'     => 'Snapshot saved for board reporting.',
        ]);
    }

    /**
     * GET /api/admin/analytics/business/snapshots
     */
    public function listSnapshots()
    {
        $snapshots = DB::table('analytics_snapshots')
            ->select('id', 'title', 'period', 'created_at', 'created_by')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $snapshots]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getSummaryKpis(string $start, string $end): array
    {
        $loans = Loan::whereBetween('created_at', [$start, $end]);

        return [
            'total_disbursals'     => (clone $loans)->where('status', 'disbursed')->count(),
            'disbursal_volume'     => (clone $loans)->where('status', 'disbursed')->sum('loan_amount'),
            'total_applications'   => (clone $loans)->count(),
            'approval_rate'        => $this->approvalRate($loans),
            'active_merchants'     => Merchant::whereRaw("LOWER(status) = 'approved'")->count(),
            'active_stores'        => Store::where('status', 'active')->count(),
            'lenders_live'         => Lender::where('status', 'active')->count(),
            'avg_loan_amount'      => (clone $loans)->where('status', 'disbursed')->avg('loan_amount'),
            'total_outstanding'    => (clone $loans)->whereIn('status', ['active', 'overdue'])->sum('outstanding_amount'),
            'npa_rate'             => $this->npaRate($start, $end),
        ];
    }

    private function getDisbursalTrend(string $start, string $end): array
    {
        return Loan::where('status', 'disbursed')
            ->whereBetween('disbursed_at', [$start, $end])
            ->selectRaw('disbursed_at::date as date, COUNT(*) as count, SUM(loan_amount) as volume')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    private function getRevenueWaterfall(string $start, string $end): array
    {
        return [
            'interest_income'    => Payment::whereBetween('paid_at', [$start, $end])->sum('interest_component'),
            'processing_fees'    => Loan::whereBetween('disbursed_at', [$start, $end])->sum('processing_fee_collected'),
            'lender_commission'  => DB::table('lender_commissions')->whereBetween('created_at', [$start, $end])->sum('amount'),
            'subvention_income'  => DB::table('subvention_records')->whereBetween('created_at', [$start, $end])->sum('amount'),
            'late_payment_fees'  => Payment::whereBetween('paid_at', [$start, $end])->sum('late_fee'),
        ];
    }

    private function getApplicationFunnel(string $start, string $end): array
    {
        $stages = ['initiated', 'kyc_submitted', 'bureau_checked', 'approved', 'esign_done', 'enach_done', 'disbursed'];
        $funnel = [];

        foreach ($stages as $stage) {
            $funnel[$stage] = Loan::whereBetween('created_at', [$start, $end])
                ->where(function ($q) use ($stage) {
                    $q->where('status', $stage)->orWhere('last_stage_reached', $stage);
                })
                ->count();
        }

        return $funnel;
    }

    private function getCohortRetention(): array
    {
        // Monthly cohort of merchant signups and their 3/6/9 month retention
        return DB::select("
            SELECT
                to_char(created_at, 'YYYY-MM') as cohort_month,
                COUNT(*) as cohort_size,
                SUM(CASE WHEN age(NOW(), created_at) >= interval '3 months' AND LOWER(status) = 'approved' THEN 1 ELSE 0 END) as retained_3m,
                SUM(CASE WHEN age(NOW(), created_at) >= interval '6 months' AND LOWER(status) = 'approved' THEN 1 ELSE 0 END) as retained_6m,
                SUM(CASE WHEN age(NOW(), created_at) >= interval '9 months' AND LOWER(status) = 'approved' THEN 1 ELSE 0 END) as retained_9m
            FROM merchants
            GROUP BY cohort_month
            ORDER BY cohort_month DESC
            LIMIT 12
        ");
    }

    private function getTopMerchants(string $start, string $end): array
    {
        return Merchant::withCount(['loans' => fn($q) => $q->where('status', 'disbursed')->whereBetween('disbursed_at', [$start, $end])])
            ->withSum(['loans' => fn($q) => $q->where('status', 'disbursed')->whereBetween('disbursed_at', [$start, $end])], 'loan_amount')
            ->orderByDesc('loans_sum_loan_amount')
            ->limit(10)
            ->get(['id', 'business_name', 'category', 'city'])
            ->toArray();
    }

    private function getYoYComparison(string $endDate): array
    {
        $thisYear  = Carbon::parse($endDate);
        $lastYear  = $thisYear->copy()->subYear();

        return [
            'this_year'    => Loan::where('status', 'disbursed')->whereYear('disbursed_at', $thisYear->year)->sum('loan_amount'),
            'last_year'    => Loan::where('status', 'disbursed')->whereYear('disbursed_at', $lastYear->year)->sum('loan_amount'),
        ];
    }

    private function getMoMComparison(string $endDate): array
    {
        $thisMonth = Carbon::parse($endDate)->startOfMonth();
        $lastMonth = $thisMonth->copy()->subMonth();

        return [
            'this_month'   => Loan::where('status', 'disbursed')->whereYear('disbursed_at', $thisMonth->year)->whereMonth('disbursed_at', $thisMonth->month)->sum('loan_amount'),
            'last_month'   => Loan::where('status', 'disbursed')->whereYear('disbursed_at', $lastMonth->year)->whereMonth('disbursed_at', $lastMonth->month)->sum('loan_amount'),
        ];
    }

    private function approvalRate($loansQuery): float
    {
        $total    = (clone $loansQuery)->count();
        $approved = (clone $loansQuery)->whereIn('status', ['approved', 'disbursed', 'esign_done', 'enach_done'])->count();

        return $total > 0 ? round(($approved / $total) * 100, 2) : 0;
    }

    private function npaRate(string $start, string $end): float
    {
        $disbursed = Loan::where('status', 'disbursed')->whereBetween('disbursed_at', [$start, $end])->count();
        $npa       = Loan::where('is_npa', true)->whereBetween('disbursed_at', [$start, $end])->count();

        return $disbursed > 0 ? round(($npa / $disbursed) * 100, 2) : 0;
    }

    private function resolveDateRange(string $period, Request $request): array
    {
        if ($period === 'custom') {
            return [$request->start_date, $request->end_date];
        }

        $map = [
            '7d'  => [now()->subDays(7)->toDateString(),   now()->toDateString()],
            '30d' => [now()->subDays(30)->toDateString(),  now()->toDateString()],
            '90d' => [now()->subDays(90)->toDateString(),  now()->toDateString()],
            '1y'  => [now()->subYear()->toDateString(),    now()->toDateString()],
        ];

        return $map[$period];
    }
}