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
            ->whereIn('status', ['Approved', 'Disbursed', 'eNACH', 'eSign'])
            ->sum('amount');

        $approvedCount = (clone $loansQuery)
            ->whereDate('created_at', $today)
            ->where('status', 'Approved')
            ->count();

        $activeStores = Store::where('merchant_id', $merchantId)
            ->where('status', 'active')
            ->count();

        return response()->json([
            'success' => true,
            'kpis' => [
                'sales_today' => $salesToday,
                'approved_loans' => $approvedCount,
                'active_stores' => $activeStores,
            ],
            'recent_loans' => (clone $loansQuery)->latest()->take(5)->get(),
        ]);
    }
}
