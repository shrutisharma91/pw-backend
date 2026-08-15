<?php

namespace App\Modules\Agent\Http\Controllers;

use App\Models\AgentTarget;
use App\Models\LoanApplication;
use App\Models\Merchant;
use Illuminate\Http\Request;

/**
 * Phase 6 Screen 43 — Field Lead & Pipeline Dashboard
 */
class DashboardController extends AgentBaseController
{
    public function metrics(Request $request)
    {
        $agentId = $this->scopedSalesExecId();
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        $period = now()->format('Y-m');

        $onboardedThisMonth = Merchant::query()
            ->where('sales_exec_id', $agentId)
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($q) {
                $q->where('status', 'Approved')
                    ->orWhere('onboarding_stage', 'approved');
            })
            ->count();

        $pendingKyc = Merchant::query()
            ->where('sales_exec_id', $agentId)
            ->whereIn('status', ['Draft', 'Submitted', 'Under Review'])
            ->where(function ($q) {
                $q->whereNull('onboarding_stage')
                    ->orWhereNotIn('onboarding_stage', ['approved']);
            })
            ->count();

        $disbursalVolume = (float) LoanApplication::query()
            ->where(function ($q) use ($agentId) {
                $q->where('sales_exec_id', $agentId)
                    ->orWhereHas('merchant', fn ($m) => $m->where('sales_exec_id', $agentId));
            })
            ->whereIn('status', ['Approved', 'Disbursed', 'Active', 'eSign', 'eNACH'])
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $target = AgentTarget::query()
            ->where('sales_exec_id', $agentId)
            ->where('period', $period)
            ->first();

        $merchantTarget = (int) ($target?->merchants_onboard_target ?? 8);
        $volumeTarget = (float) ($target?->disbursal_volume_target ?? 2000000);
        $progressPct = $merchantTarget > 0
            ? round(($onboardedThisMonth / $merchantTarget) * 100, 1)
            : 0;

        $pipeline = [
            'lead' => $this->stageCount($agentId, ['lead'], ['Draft']),
            'docs_collected' => $this->stageCount($agentId, ['docs_collected'], ['Submitted']),
            'inspection_done' => $this->stageCount($agentId, ['inspection_done'], ['Under Review']),
            'approved' => $this->stageCount($agentId, ['approved'], ['Approved']),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'kpis' => [
                    'merchants_onboarded_this_month' => $onboardedThisMonth,
                    'pending_kyc_approvals' => $pendingKyc,
                    'total_disbursal_volume' => $disbursalVolume,
                ],
                'target' => [
                    'merchants_onboard_target' => $merchantTarget,
                    'disbursal_volume_target' => $volumeTarget,
                    'progress_pct' => min(100, $progressPct),
                ],
                'pipeline' => $pipeline,
                'quick_actions' => [
                    'onboard_merchant' => '/api/v1/agent/merchant/onboard',
                    'log_store_visit' => '/api/v1/agent/audits/checkin',
                ],
            ],
        ]);
    }

    /**
     * @param  list<string>  $stages
     * @param  list<string>  $statuses
     */
    private function stageCount(int $agentId, array $stages, array $statuses): int
    {
        return Merchant::query()
            ->where('sales_exec_id', $agentId)
            ->where(function ($q) use ($stages, $statuses) {
                $q->whereIn('onboarding_stage', $stages)
                    ->orWhere(function ($inner) use ($stages, $statuses) {
                        $inner->where(function ($s) {
                            $s->whereNull('onboarding_stage')->orWhere('onboarding_stage', '');
                        })->whereIn('status', $statuses);
                    });
            })
            ->count();
    }
}
