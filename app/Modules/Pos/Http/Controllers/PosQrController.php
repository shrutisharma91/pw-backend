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
                'pdf_download_url' => url("/api/v1/pos/qr/download-pdf")
            ]
        ]);
    }

    public function downloadPdf(Request $request)
    {
        // Mock PDF generation for QR Standee
        // Normally this would use dompdf or snappy to render a view with the QR code and return a download response
        return response()->json([
            'success' => true,
            'message' => 'PDF generated.',
            'download_link' => 'https://example.com/dummy-qr-standee.pdf'
        ]);
    }
}
