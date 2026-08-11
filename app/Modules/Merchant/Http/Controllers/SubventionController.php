<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\EmiType;
use Illuminate\Http\Request;

class SubventionController extends MerchantBaseController
{
    public function index(Request $request)
    {
        // For subvention matrix, we list EMI types (e.g. Zero Cost, Standard) 
        // that are active for the platform and potentially specific configurations for this merchant.
        $emiTypes = EmiType::all();

        return response()->json([
            'success' => true,
            'data'    => $emiTypes,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Merchant updates their subvention sharing percentage or toggles subvention
        // In a real app this would store merchant-specific subvention preferences.
        $validated = $request->validate([
            'merchant_contribution_pct' => 'required|numeric|min:0|max:100',
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Subvention updated.',
            'data' => $validated
        ]);
    }
}
