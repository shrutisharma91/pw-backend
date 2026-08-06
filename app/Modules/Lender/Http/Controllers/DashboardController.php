<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\Collection;
use App\Models\Disbursal;
use App\Models\LoanApplication;
use Illuminate\Http\Request;

class DashboardController extends LenderBaseController
{
    public function metrics(Request $request)
    {
        $lenderId = $this->scopedLenderId();

        $applications = LoanApplication::query()->where('lender_id', $lenderId);
        $approved = (clone $applications)->where('status', 'Approved')->count();
        $total = (clone $applications)->count();
        $pendingApproval = (clone $applications)->whereIn('status', ['Initiated', 'Underwriting'])->count();

        $disbursedVolume = Disbursal::query()
            ->where('lender_id', $lenderId)
            ->where('status', 'Completed')
            ->sum('amount');

        $activePortfolio = LoanApplication::query()
            ->where('lender_id', $lenderId)
            ->whereIn('status', ['Approved', 'Disbursed', 'Active'])
            ->sum('amount');

        $npaCount = Collection::query()
            ->whereHas('loanApplication', fn ($q) => $q->where('lender_id', $lenderId))
            ->where('npa_status', 'NPA')
            ->count();

        $collectionsTotal = Collection::query()
            ->whereHas('loanApplication', fn ($q) => $q->where('lender_id', $lenderId))
            ->count();

        $monthlyTrend = Disbursal::query()
            ->where('lender_id', $lenderId)
            ->where('status', 'Completed')
            ->get()
            ->groupBy(fn ($row) => $row->created_at?->format('Y-m'))
            ->map(fn ($group, $month) => ['month' => $month, 'total' => (float) $group->sum('amount')])
            ->values()
            ->take(6);

        return response()->json([
            'success' => true,
            'data' => [
                'total_disbursed_volume' => (float) $disbursedVolume,
                'active_portfolio_value' => (float) $activePortfolio,
                'approval_rate_pct' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
                'npl_pct' => $collectionsTotal > 0 ? round(($npaCount / $collectionsTotal) * 100, 2) : 0,
                'monthly_disbursal_trend' => $monthlyTrend,
                'pending_approval_count' => $pendingApproval,
                'sla_status' => (clone $applications)->where('sla_breached', true)->exists() ? 'breached' : 'healthy',
            ],
        ]);
    }
}
