<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MerchantAgreement;

class MerchantAgreementController extends Controller
{
    public function generate(Request $request, $merchantId)
    {
        // Screen 17 - Merchant Agreement Management
        // Logic to generate PDF from template and save agreement
        $agreement = MerchantAgreement::create([
            'merchant_id' => $merchantId,
            'status' => 'Generated',
            'version' => 1
        ]);
        
        return response()->json(['message' => 'Agreement generated successfully', 'agreement' => $agreement]);
    }
}
