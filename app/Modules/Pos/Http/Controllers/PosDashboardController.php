<?php

namespace App\Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LoanApplication;

class PosDashboardController extends PosBaseController
{
    /**
     * Get dashboard metrics for the store.
     */
    public function index(Request $request)
    {
        $storeId = $this->getStoreId($request);
        $merchantId = $this->getMerchantId($request);

        // Fetch loans created at this store today
        $todayLoans = LoanApplication::where('store_id', $storeId)
            ->whereDate('created_at', today())
            ->count();

        // Fetch pending eSigns (Status: 'pending_esign')
        $pendingEsighns = LoanApplication::where('store_id', $storeId)
            ->where('status', 'pending_esign')
            ->count();

        // Total volume today
        $todayVolume = LoanApplication::where('store_id', $storeId)
            ->whereDate('created_at', today())
            ->whereIn('status', ['approved', 'disbursed', 'active', 'pending_esign'])
            ->sum('amount');

        // Recent 5 loans for quick view
        $recentLoans = LoanApplication::where('store_id', $storeId)
            ->with(['customer', 'product'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'metrics' => [
                    'today_applications' => $todayLoans,
                    'pending_esigns'     => $pendingEsighns,
                    'today_volume'       => $todayVolume,
                ],
                'recent_loans' => $recentLoans->map(function ($loan) {
                    return [
                        'id' => $loan->id,
                        'customer_name' => $loan->customer->name ?? 'N/A',
                        'product_name'  => $loan->product->name ?? 'Custom Product',
                        'amount'        => $loan->amount,
                        'status'        => $loan->status,
                        'created_at'    => $loan->created_at->toIso8601String(),
                    ];
                })
            ]
        ]);
    }
}
