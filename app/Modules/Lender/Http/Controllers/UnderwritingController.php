<?php

namespace App\Modules\Lender\Http\Controllers;

use App\Models\LoanTimelineEvent;
use Illuminate\Http\Request;

class UnderwritingController extends LenderBaseController
{
    public function creditFile(int $id)
    {
        $app = $this->findLoanApplication($id);
        $payload = $app->application_payload ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'application_id' => $app->id,
                'bureau_snapshot' => [
                    'cibil' => $payload['cibil'] ?? null,
                    'bureau_status' => $payload['bureau_status'] ?? 'Unknown',
                ],
                'bank_analytics' => [
                    'monthly_income' => $payload['monthly_income'] ?? null,
                ],
                'foir' => $payload['dti'] ?? $payload['foir'] ?? null,
                'fraud_flags' => [$payload['flag_reason'] ?? null],
                'amount' => $app->amount,
                'status' => $app->status,
            ],
        ]);
    }

    public function decision(Request $request, int $id)
    {
        $request->validate([
            'decision' => 'required|in:approve,reject,refer',
            'reason' => 'required|string|max:2000',
            'approved_amount' => 'nullable|numeric|min:0',
        ]);

        $app = $this->findLoanApplication($id);

        $statusMap = [
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'refer' => 'Underwriting',
        ];

        $app->status = $statusMap[$request->decision];
        if ($request->decision === 'approve' && $request->filled('approved_amount')) {
            $app->amount = $request->approved_amount;
        }
        $app->save();

        LoanTimelineEvent::create([
            'loan_application_id' => $app->id,
            'event_name' => 'Lender Decision: ' . $request->decision,
            'stage' => $app->status,
            'payload' => ['reason' => $request->reason],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Decision recorded.',
            'data' => $app->fresh(),
        ]);
    }
}
