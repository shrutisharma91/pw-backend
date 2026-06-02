<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MerchantAgreement;
use App\Models\Merchant;

class MerchantAgreementController extends Controller
{
    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/agreement",
        summary: "Generate Merchant Agreement",
        security: [["sanctum" => []]],
        tags: ["MerchantAgreement"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function generate(Request $request, $id)
    {
        $merchant = Merchant::findOrFail($id);
        
        // Implementation: Generate Agreement Record
        $version = MerchantAgreement::where('merchant_id', $id)->count() + 1;
        
        $agreement = MerchantAgreement::create([
            'merchant_id' => $merchant->id,
            'status' => 'Generated',
            'version' => $version
        ]);
        
        return response()->json([
            'message' => 'Agreement generated successfully', 
            'agreement' => $agreement,
            'download_url' => url("/api/v1/admin/merchants/{$id}/agreement/{$agreement->id}/download")
        ]);
    }
}
