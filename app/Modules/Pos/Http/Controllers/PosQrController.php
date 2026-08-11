<?php

namespace App\Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;

class PosQrController extends PosBaseController
{
    /**
     * Get Store QR code data (Screen 42)
     */
    public function show(Request $request)
    {
        $storeId = $this->getStoreId($request);
        $merchantId = $this->getMerchantId($request);
        
        // Generate the URL that the customer should hit when they scan the QR code.
        // It points to the Customer Portal with the store_id tracking param.
        $baseUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $customerApplyUrl = "{$baseUrl}/customer/auth?store_id={$storeId}&merchant_id={$merchantId}";

        return response()->json([
            'success' => true,
            'data'    => [
                'store_id' => $storeId,
                'merchant_id' => $merchantId,
                'qr_url' => $customerApplyUrl,
                // In a real app we might return a base64 PNG of a generated QR code, 
                // but for React it's easier to generate the QR SVG on the frontend using the URL.
            ]
        ]);
    }
}
