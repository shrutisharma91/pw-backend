<?php

namespace App\Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoanApplication;

class PosLedgerController extends PosBaseController
{
    /**
     * Get the daily disbursal ledger for the store (Screen 40)
     */
    public function index(Request $request)
    {
        $storeId = $this->getStoreId($request);
        $date = $request->query('date', today()->toDateString());
        
        $query = LoanApplication::where('store_id', $storeId)
            ->whereIn('status', ['approved', 'disbursed', 'active'])
            ->whereDate('created_at', $date)
            ->with(['customer', 'product']);

        $loans = $query->orderBy('created_at', 'desc')->get();

        // Calculate totals
        $totalDisbursed = $loans->sum('amount');
        $settledAmount = $loans->where('status', 'disbursed')->sum('amount');
        $pendingSettlement = $totalDisbursed - $settledAmount;

        return response()->json([
            'success' => true,
            'data'    => [
                'date' => $date,
                'metrics' => [
                    'total_loans' => $loans->count(),
                    'total_disbursed' => $totalDisbursed,
                    'settled_amount' => $settledAmount,
                    'pending_settlement' => $pendingSettlement
                ],
                'ledger' => $loans->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'application_number' => 'APP-' . str_pad($loan->id, 5, '0', STR_PAD_LEFT),
                        'customer_name' => $loan->customer->name ?? 'N/A',
                        'amount' => $loan->amount,
                        'status' => $loan->status,
                        'time' => $loan->created_at->format('H:i:s'),
                    ];
                })
            ]
        ]);
    }
}
