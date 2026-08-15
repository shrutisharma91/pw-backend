<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\EmiType;
use Illuminate\Http\Request;

class SubventionController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $matrices = \Illuminate\Support\Facades\DB::table('subvention_matrices')
            ->where('merchant_id', $this->scopedMerchantId())
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $matrices,
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'merchant_split' => 'required|numeric|min:0|max:100',
            'lender_split'   => 'required|numeric|min:0|max:100',
        ]);

        if ($validated['merchant_split'] + $validated['lender_split'] !== 100.0) {
            return response()->json(['success' => false, 'message' => 'Splits must sum to 100'], 422);
        }

        $matrix = \Illuminate\Support\Facades\DB::table('subvention_matrices')
            ->where('merchant_id', $this->scopedMerchantId())
            ->where('id', $id)
            ->update([
                'merchant_split' => $validated['merchant_split'],
                'lender_split' => $validated['lender_split'],
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true, 
            'message' => 'Subvention updated.',
            'data' => $validated
        ]);
    }
}
