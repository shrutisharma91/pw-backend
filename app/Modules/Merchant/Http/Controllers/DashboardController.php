<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends MerchantBaseController
{
    public function index()
    {
        $merchantId = $this->scopedMerchantId();
        $storeId = $this->scopedStoreId();

        $today = Carbon::today();
        $countedStatuses = ['Approved', 'Disbursed', 'eNACH', 'eSign', 'Active'];

        $loansQuery = LoanApplication::where('merchant_id', $merchantId);
        if ($storeId) {
            $loansQuery->where('store_id', $storeId);
        }

        $salesToday = (clone $loansQuery)
            ->whereDate('created_at', $today)
            ->whereIn('status', $countedStatuses)
            ->sum('amount');

        $approvedCount = (clone $loansQuery)
            ->whereDate('created_at', $today)
            ->where('status', 'Approved')
            ->count();

        $activeStores = Store::where('merchant_id', $merchantId)
            ->where('status', 'active')
            ->count();

        $netSettlementPending = (clone $loansQuery)
            ->whereIn('status', $countedStatuses)
            ->sum('amount') * 0.1;

        $salesTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $amount = (clone $loansQuery)
                ->whereDate('created_at', $date)
                ->whereIn('status', $countedStatuses)
                ->sum('amount');
            $salesTrend[] = [
                'date' => $date->format('Y-m-d'),
                'amount' => $amount,
            ];
        }

        $topStoresQuery = DB::table('loan_applications')
            ->join('stores', 'loan_applications.store_id', '=', 'stores.id')
            ->where('loan_applications.merchant_id', $merchantId);

        if ($storeId) {
            $topStoresQuery->where('loan_applications.store_id', $storeId);
        }

        $topStores = $topStoresQuery
            ->select('stores.name', DB::raw('SUM(loan_applications.amount) as total_sales'))
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
            'recent_loans' => (clone $loansQuery)->with(['customer', 'store'])->latest()->take(5)->get(),
        ]);
    }
}
