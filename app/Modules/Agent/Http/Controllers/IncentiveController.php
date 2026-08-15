<?php

namespace App\Modules\Agent\Http\Controllers;

use App\Models\AgentIncentive;
use App\Models\AgentTarget;
use App\Models\Merchant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Phase 6 Screen 47 — Agent Incentive Payout Tracker
 */
class IncentiveController extends AgentBaseController
{
    public function statement(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));
        $payload = $this->statementPayload($period);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function download(Request $request)
    {
        $period = $request->query('period', now()->format('Y-m'));
        $payload = $this->statementPayload($period);
        $agent = User::query()->find($this->scopedSalesExecId());

        $lines = [
            'FinZ LMS — Agent Incentive Payout Statement',
            '===========================================',
            'Agent: ' . ($agent?->name ?? ('Exec #' . $this->scopedSalesExecId())),
            'Period: ' . $period,
            'Generated: ' . now()->toDateTimeString(),
            '',
            'Total earnings: INR ' . number_format($payload['kpis']['total_earnings'], 2),
            'Pending commission: INR ' . number_format($payload['kpis']['pending_commission'], 2),
            'Disbursed loans originated: ' . $payload['kpis']['disbursed_loans_originated'],
            'Tier: ' . $payload['tier']['name'] . ' (' . $payload['tier']['bonus_pct'] . '% bonus)',
            '',
            'Loan ID | Merchant | Loan Value | Commission | Status',
        ];

        foreach ($payload['breakdown'] as $row) {
            $lines[] = sprintf(
                '%s | %s | %s | %s | %s',
                $row['loan_id'],
                $row['merchant_name'],
                $row['loan_value'],
                $row['commission'],
                $row['payout_status']
            );
        }

        $pdf = $this->minimalPdf(implode("\n", $lines));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="agent-incentive-' . $period . '.pdf"',
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function statementPayload(string $period): array
    {
        $agentId = $this->scopedSalesExecId();

        $rows = AgentIncentive::query()
            ->where('sales_exec_id', $agentId)
            ->where('period', $period)
            ->with(['merchant:id,business_name', 'loanApplication:id,amount,status'])
            ->latest()
            ->get();

        $total = (float) $rows->sum('commission_amount');
        $pending = (float) $rows->where('payout_status', 'pending')->sum('commission_amount');
        $disbursedCount = $rows->count();

        $onboarded = Merchant::query()
            ->where('sales_exec_id', $agentId)
            ->where('created_at', '>=', Carbon::createFromFormat('Y-m', $period)->startOfMonth())
            ->where('created_at', '<=', Carbon::createFromFormat('Y-m', $period)->endOfMonth())
            ->where(function ($q) {
                $q->where('status', 'Approved')->orWhere('onboarding_stage', 'approved');
            })
            ->count();

        $tier = $this->tierForCount($onboarded);
        $target = AgentTarget::query()
            ->where('sales_exec_id', $agentId)
            ->where('period', $period)
            ->first();

        return [
            'period' => $period,
            'kpis' => [
                'total_earnings' => $total,
                'pending_commission' => $pending,
                'disbursed_loans_originated' => $disbursedCount,
            ],
            'tier' => [
                'name' => $tier['name'],
                'bonus_pct' => $tier['bonus'],
                'onboarded_this_month' => $onboarded,
                'next_tier' => $tier['next'],
            ],
            'target' => [
                'merchants_onboard_target' => (int) ($target?->merchants_onboard_target ?? 8),
                'disbursal_volume_target' => (float) ($target?->disbursal_volume_target ?? 2000000),
            ],
            'breakdown' => $rows->map(function (AgentIncentive $row) {
                return [
                    'id' => $row->id,
                    'loan_id' => $row->loan_application_id,
                    'merchant_name' => $row->merchant?->business_name ?? 'N/A',
                    'loan_value' => (float) $row->loan_amount,
                    'commission' => (float) $row->commission_amount,
                    'commission_rate_pct' => (float) $row->commission_rate_pct,
                    'payout_status' => $row->payout_status,
                    'tier' => $row->tier,
                ];
            })->values(),
        ];
    }

    /**
     * @return array{name: string, bonus: float, next: string|null}
     */
    private function tierForCount(int $onboarded): array
    {
        if ($onboarded >= 5) {
            return ['name' => 'Gold', 'bonus' => 0.5, 'next' => null];
        }
        if ($onboarded >= 3) {
            return ['name' => 'Silver', 'bonus' => 0.25, 'next' => 'Gold at 5 onboarded'];
        }

        return ['name' => 'Bronze', 'bonus' => 0.0, 'next' => 'Silver at 3 onboarded'];
    }

    private function minimalPdf(string $text): string
    {
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $lines = explode("\n", $escaped);
        $content = "BT /F1 11 Tf 50 750 Td\n";
        foreach ($lines as $i => $line) {
            if ($i > 0) {
                $content .= "0 -16 Td\n";
            }
            $content .= "({$line}) Tj\n";
        }
        $content .= 'ET';

        $objects = [];
        $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';
        $objects[] = '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj';
        $objects[] = '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R >> >> >>endobj';
        $objects[] = '4 0 obj<< /Length ' . strlen($content) . " >>stream\n{$content}\nendstream endobj";
        $objects[] = '5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj . "\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer<< /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
