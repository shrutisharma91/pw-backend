<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lender;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 11 — Screen 46: Lender & Loan Analytics
 * Lender scorecard, NPA, SLA adherence, rejection breakdown, comparison
 */
class LenderAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:analytics.lender.view']);
    }

    /**
     * GET /api/admin/analytics/lender
     * Full lender analytics payload
     */
    public function index(Request $request)
    {
        $request->validate([
            'period'     => 'nullable|in:7d,30d,90d,1y',
            'lender_id'  => 'nullable|exists:lenders,id',
        ]);

        $period   = $request->period ?? '30d';
        $lenderId = $request->lender_id;

        [$start, $end] = $this->resolveDateRange($period);

        $cacheKey = "analytics.lender.{$period}.{$lenderId}";

        $data = Cache::remember($cacheKey, 300, function () use ($start, $end, $lenderId) {
            return [
                'scorecards'         => $this->getLenderScorecards($start, $end, $lenderId),
                'rejection_reasons'  => $this->getRejectionReasons($start, $end, $lenderId),
                'tenure_mix'         => $this->getTenureMix($start, $end, $lenderId),
                'category_mix'       => $this->getCategoryMix($start, $end, $lenderId),
                'sla_performance'    => $this->getSlaPerformance($start, $end, $lenderId),
                'comparison'         => $this->getLenderComparison($start, $end),
                'npa_threshold_alerts' => $this->getNpaAlerts(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/admin/analytics/lender/{id}/scorecard
     * Single lender deep-dive scorecard
     */
    public function scorecard(Request $request, int $id)
    {
        $lender = Lender::findOrFail($id);

        [$start, $end] = $this->resolveDateRange($request->period ?? '30d');

        $loans = Loan::where('lender_id', $id)->whereBetween('created_at', [$start, $end]);

        return response()->json([
            'success' => true,
            'data'    => [
                'lender'              => $lender->only('id', 'name', 'logo', 'status'),
                'total_applications'  => (clone $loans)->count(),
                'approved'            => (clone $loans)->where('lender_status', 'approved')->count(),
                'rejected'            => (clone $loans)->where('lender_status', 'rejected')->count(),
                'disbursed'           => (clone $loans)->where('status', 'disbursed')->count(),
                'approval_rate'       => $this->calcApprovalRate($loans),
                'volume_share'        => $this->calcVolumeShare($id, $start, $end),
                'avg_disbursal_days'  => $this->avgDisbursalDays($id, $start, $end),
                'npa_count'           => (clone $loans)->where('is_npa', true)->count(),
                'npa_value'           => (clone $loans)->where('is_npa', true)->sum('outstanding_amount'),
                'p50_latency_ms'      => $this->getLatencyPercentile($id, 50),
                'p95_latency_ms'      => $this->getLatencyPercentile($id, 95),
                'sla_breach_count'    => DB::table('lender_sla_logs')->where('lender_id', $id)->where('is_breached', true)->whereBetween('created_at', [$start, $end])->count(),
                'rejection_reasons'   => $this->getRejectionReasons($start, $end, $id),
                'tenure_mix'          => $this->getTenureMix($start, $end, $id),
                'category_mix'        => $this->getCategoryMix($start, $end, $id),
            ],
        ]);
    }

    /**
     * GET /api/admin/analytics/lender/export
     * Export lender scorecard for monthly review
     */
    public function export(Request $request)
    {
        $request->validate([
            'period'    => 'required|in:7d,30d,90d,1y',
            'format'    => 'required|in:csv,xlsx,pdf',
            'lender_id' => 'nullable|exists:lenders,id',
        ]);

        // Dispatch export job
        dispatch(new \App\Jobs\ExportLenderScorecardJob(
            $request->period,
            $request->format,
            $request->lender_id,
            auth()->id()
        ));

        return response()->json([
            'success' => true,
            'message' => 'Export queued. You will receive a notification when ready.',
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getLenderScorecards(string $start, string $end, ?int $lenderId): array
    {
        $query = Lender::with(['loans' => fn($q) => $q->whereBetween('created_at', [$start, $end])]);

        if ($lenderId) {
            $query->where('id', $lenderId);
        }

        return $query->get()->map(function ($lender) {
            $loans = $lender->loans;
            $total = $loans->count();

            return [
                'id'             => $lender->id,
                'name'           => $lender->name,
                'logo'           => $lender->logo_url,
                'status'         => $lender->status,
                'total'          => $total,
                'approved'       => $loans->where('lender_status', 'approved')->count(),
                'approval_rate'  => $total > 0 ? round($loans->where('lender_status', 'approved')->count() / $total * 100, 2) : 0,
                'volume'         => $loans->where('status', 'disbursed')->sum('loan_amount'),
                'npa_rate'       => $total > 0 ? round($loans->where('is_npa', true)->count() / $total * 100, 2) : 0,
                'sla_adherence'  => $this->calcSlaAdherence($lender->id),
            ];
        })->toArray();
    }

    private function getRejectionReasons(string $start, string $end, ?int $lenderId): array
    {
        $query = DB::table('loan_rejection_logs')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('rejection_reason, COUNT(*) as count')
            ->groupBy('rejection_reason')
            ->orderByDesc('count');

        if ($lenderId) {
            $query->where('lender_id', $lenderId);
        }

        return $query->limit(10)->get()->toArray();
    }

    private function getTenureMix(string $start, string $end, ?int $lenderId): array
    {
        $query = Loan::where('status', 'disbursed')
            ->whereBetween('disbursed_at', [$start, $end])
            ->selectRaw('tenure_months, COUNT(*) as count, SUM(loan_amount) as volume')
            ->groupBy('tenure_months')
            ->orderBy('tenure_months');

        if ($lenderId) {
            $query->where('lender_id', $lenderId);
        }

        return $query->get()->toArray();
    }

    private function getCategoryMix(string $start, string $end, ?int $lenderId): array
    {
        $query = Loan::where('status', 'disbursed')
            ->whereBetween('disbursed_at', [$start, $end])
            ->selectRaw('product_category, COUNT(*) as count, SUM(loan_amount) as volume')
            ->groupBy('product_category')
            ->orderByDesc('volume');

        if ($lenderId) {
            $query->where('lender_id', $lenderId);
        }

        return $query->limit(10)->get()->toArray();
    }

    private function getSlaPerformance(string $start, string $end, ?int $lenderId): array
    {
        $query = DB::table('lender_sla_logs')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('lender_id, AVG(response_time_ms) as avg_response, COUNT(*) as total, SUM(CASE WHEN is_breached THEN 1 ELSE 0 END) as breaches')
            ->groupBy('lender_id');

        if ($lenderId) {
            $query->where('lender_id', $lenderId);
        }

        return $query->get()->toArray();
    }

    private function getLenderComparison(string $start, string $end): array
    {
        return Lender::where('status', 'active')->get()->map(function ($lender) use ($start, $end) {
            $loans = Loan::where('lender_id', $lender->id)->whereBetween('created_at', [$start, $end]);
            $total = (clone $loans)->count();

            return [
                'lender_id'     => $lender->id,
                'name'          => $lender->name,
                'approval_rate' => $total > 0 ? round((clone $loans)->where('lender_status', 'approved')->count() / $total * 100, 1) : 0,
                'volume'        => (clone $loans)->where('status', 'disbursed')->sum('loan_amount'),
                'npa_rate'      => $total > 0 ? round((clone $loans)->where('is_npa', true)->count() / $total * 100, 2) : 0,
                'avg_latency'   => $this->getLatencyPercentile($lender->id, 50),
            ];
        })->toArray();
    }

    private function getNpaAlerts(): array
    {
        return DB::table('lenders')
            ->join('loans', 'lenders.id', '=', 'loans.lender_id')
            ->where('loans.is_npa', true)
            ->groupBy('lenders.id', 'lenders.name', 'lenders.npa_threshold')
            ->havingRaw('COUNT(*) / (SELECT COUNT(*) FROM loans WHERE lender_id = lenders.id) * 100 > lenders.npa_threshold')
            ->select('lenders.id', 'lenders.name', 'lenders.npa_threshold', DB::raw('COUNT(*) as npa_count'))
            ->get()
            ->toArray();
    }

    private function calcApprovalRate($loansQuery): float
    {
        $total    = (clone $loansQuery)->count();
        $approved = (clone $loansQuery)->where('lender_status', 'approved')->count();
        return $total > 0 ? round($approved / $total * 100, 2) : 0;
    }

    private function calcVolumeShare(int $lenderId, string $start, string $end): float
    {
        $lenderVol = Loan::where('lender_id', $lenderId)->where('status', 'disbursed')->whereBetween('disbursed_at', [$start, $end])->sum('loan_amount');
        $totalVol  = Loan::where('status', 'disbursed')->whereBetween('disbursed_at', [$start, $end])->sum('loan_amount');
        return $totalVol > 0 ? round($lenderVol / $totalVol * 100, 2) : 0;
    }

    private function avgDisbursalDays(int $lenderId, string $start, string $end): float
    {
        return DB::table('loans')
            ->where('lender_id', $lenderId)
            ->where('status', 'disbursed')
            ->whereBetween('disbursed_at', [$start, $end])
            ->whereNotNull('approved_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (disbursed_at - approved_at)) / 86400) as avg_days')
            ->value('avg_days') ?? 0;
    }

    private function getLatencyPercentile(int $lenderId, int $percentile): float
    {
        // Uses pre-aggregated stats table populated by monitoring jobs
        return DB::table('lender_api_stats')
            ->where('lender_id', $lenderId)
            ->where('percentile', $percentile)
            ->orderByDesc('recorded_at')
            ->value('latency_ms') ?? 0;
    }

    private function calcSlaAdherence(int $lenderId): float
    {
        $total    = DB::table('lender_sla_logs')->where('lender_id', $lenderId)->count();
        $met      = DB::table('lender_sla_logs')->where('lender_id', $lenderId)->where('is_breached', false)->count();
        return $total > 0 ? round($met / $total * 100, 1) : 100;
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