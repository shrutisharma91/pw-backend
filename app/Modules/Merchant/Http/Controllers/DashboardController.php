<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $merchantId = $this->scopedMerchantId();
        $storeId = $this->scopedStoreId();

        $today = Carbon::today();

        $loansQuery = LoanApplication::where('merchant_id', $merchantId);
        if ($storeId) {
            $loansQuery->where('store_id', $storeId);
        }

        $salesToday = (clone $loansQuery)
            ->whereDate('created_at', $today)
            ->whereIn('status', ['Approved', 'Disbursed', 'eNACH', 'eSign', 'Active'])
            ->sum('amount');

        $approvedCount = (clone $loansQuery)
            ->whereDate('created_at', $today)
            ->where('status', 'Approved')
            ->count();

        $activeStores = Store::where('merchant_id', $merchantId)
            ->where('status', 'active')
            ->count();

        // Net Settlement Pending (Mock/Simplified logic for Phase 4)
        $netSettlementPending = (clone $loansQuery)
            ->whereIn('status', ['Approved', 'Disbursed', 'eNACH', 'eSign', 'Active'])
            ->sum('amount') * 0.1; // Placeholder for unpaid settlement 

        // Sales Trend (7 days)
        $salesTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $amount = (clone $loansQuery)
                ->whereDate('created_at', $date)
                ->whereIn('status', ['Approved', 'Disbursed', 'eNACH', 'eSign', 'Active'])
                ->sum('amount');
            $salesTrend[] = [
                'date' => $date->format('Y-m-d'),
                'amount' => $amount
            ];
        }

        // Top Stores
        $topStores = \Illuminate\Support\Facades\DB::table('loan_applications')
            ->join('stores', 'loan_applications.store_id', '=', 'stores.id')
            ->where('loan_applications.merchant_id', $merchantId)
            ->select('stores.name', \Illuminate\Support\Facades\DB::raw('SUM(loan_applications.amount) as total_sales'))
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'kpis' => [
                'sales_today' => $salesToday,
                'approved_loans' => $approvedCount,
                'active_stores' => $activeStores,
                'net_settlement_pending' => $netSettlementPending,
            ],
            'sales_trend' => $salesTrend,
            'top_stores' => $topStores,
            'recent_loans' => (clone $loansQuery)->latest()->take(5)->get(),
        ]);
    }
}
