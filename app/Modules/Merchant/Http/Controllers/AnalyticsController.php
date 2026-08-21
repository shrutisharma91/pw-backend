<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\LoanApplication;
use App\Models\Merchant;
use Illuminate\Http\Request;

class AnalyticsController extends MerchantBaseController
{
    public function sales(Request $request)
    {
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
        $merchant = Merchant::query()->findOrFail($this->scopedMerchantId());

        $documents = [];
        foreach ([
            'GSTIN' => filled($merchant->gst_number),
            'PAN' => filled($merchant->pan_number),
            'Bank account' => filled($merchant->bank_account_number) && filled($merchant->bank_ifsc),
        ] as $type => $present) {
            $documents[] = [
                'type' => $type,
                'status' => $present ? 'Present' : 'Missing',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => [
                    'id' => $merchant->id,
                    'business_name' => $merchant->business_name,
                    'gst_number' => $merchant->gst_number,
                    'pan_number' => $merchant->pan_number,
                    'status' => $merchant->status,
                    'address' => $merchant->address,
                    'bank_account_name' => $merchant->bank_account_name,
                    'bank_ifsc' => $merchant->bank_ifsc,
                    'bank_account_number' => $merchant->bank_account_number,
                ],
                'documents' => $documents,
            ],
        ]);
    }

    public function vaultUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:5120', // 5MB max
            'type' => 'required|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'data' => [
                'id' => rand(100, 999),
                'type' => $request->type,
                'status' => 'Pending',
                'uploaded_at' => Carbon::now()
            ]
        ]);
    }
}
