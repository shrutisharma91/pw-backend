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

    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}/agreements",
        summary: "List Merchant Agreements",
        security: [["sanctum" => []]],
        tags: ["MerchantAgreement"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index($id)
    {
        $agreements = MerchantAgreement::where('merchant_id', $id)->orderBy('version', 'desc')->get();
        return response()->json($agreements);
    }

    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}/agreements/{agreement_id}/preview",
        summary: "Preview Merchant Agreement",
        security: [["sanctum" => []]],
        tags: ["MerchantAgreement"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "agreement_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function preview($id, $agreement_id)
    {
        $agreement = MerchantAgreement::where('merchant_id', $id)->findOrFail($agreement_id);
        // Mock preview HTML or PDF URL
        return response()->json(['html_content' => "<h1>Merchant Agreement Version {$agreement->version}</h1><p>...</p>"]);
    }

    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}/agreements/{agreement_id}/esign-status",
        summary: "Check eSign Status",
        security: [["sanctum" => []]],
        tags: ["MerchantAgreement"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "agreement_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function esignStatus($id, $agreement_id)
    {
        $agreement = MerchantAgreement::where('merchant_id', $id)->findOrFail($agreement_id);
        // Mock status from external eSign provider
        return response()->json([
            'status' => 'Pending',
            'signed_by_merchant' => false,
            'signed_by_admin' => false,
            'last_ping' => now()
        ]);
    }
}
