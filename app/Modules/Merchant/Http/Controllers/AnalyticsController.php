<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\LoanApplication;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends MerchantBaseController
{
    public function sales(Request $request)
    {
        // Simple sales funnel mock based on loan statuses for the merchant
        $merchantId = $this->scopedMerchantId();
        
        $totalLoans = LoanApplication::where('merchant_id', $merchantId)->count();
        $approvedLoans = LoanApplication::where('merchant_id', $merchantId)
                            ->whereIn('status', ['Approved', 'Disbursed', 'eNACH', 'eSign'])->count();
        $rejectedLoans = LoanApplication::where('merchant_id', $merchantId)
                            ->where('status', 'Rejected')->count();

        $conversionRate = $totalLoans > 0 ? round(($approvedLoans / $totalLoans) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data'    => [
                'total_applications' => $totalLoans,
                'approved' => $approvedLoans,
                'rejected' => $rejectedLoans,
                'conversion_rate_pct' => $conversionRate,
            ]
        ]);
    }

    public function vault(Request $request)
    {
        // For screen 37: Merchant Analytics & Vault
        // Returns the compliance documents (GSTIN, PAN) uploaded by the merchant.
        // Assuming merchants table or related merchant_documents table.
        // For this phase, returning mock structure to connect to frontend.
        
        return response()->json([
            'success' => true,
            'data'    => [
                ['id' => 1, 'type' => 'GSTIN', 'status' => 'Verified', 'uploaded_at' => Carbon::now()->subDays(10)],
                ['id' => 2, 'type' => 'PAN', 'status' => 'Verified', 'uploaded_at' => Carbon::now()->subDays(10)],
                ['id' => 3, 'type' => 'Bank Statement', 'status' => 'Pending', 'uploaded_at' => Carbon::now()->subDays(1)],
            ]
        ]);
    }
}
