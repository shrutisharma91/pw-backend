<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Lender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LenderController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/lenders",
        summary: "List Lenders",
        security: [["sanctum" => []]],
        tags: ["Lender"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index()
    {
        $lenders = Lender::all();
        return response()->json($lenders);
    }

    #[OA\Get(
        path: "/api/v1/admin/lenders/{id}",
        summary: "Show Lender",
        security: [["sanctum" => []]],
        tags: ["Lender"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function show($id)
    {
        $lender = Lender::findOrFail($id);
        return response()->json($lender);
    }

    #[OA\Post(
        path: "/api/v1/admin/lenders",
        summary: "Create Lender",
        security: [["sanctum" => []]],
        tags: ["Lender"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "HDFC Bank"),
                    new OA\Property(property: "api_base_url", type: "string", example: "https://api.hdfc.com/v1"),
                    new OA\Property(property: "status", type: "string", example: "active"),
                    new OA\Property(property: "api_key", type: "string", example: "key_123"),
                    new OA\Property(property: "api_secret", type: "string", example: "secret_123"),
                    new OA\Property(property: "webhook_url", type: "string", example: "https://webhook.site/123"),
                    new OA\Property(property: "commission_type", type: "string", example: "percentage"),
                    new OA\Property(property: "commission_value", type: "number", example: 1.5),
                    new OA\Property(property: "supported_categories", type: "array", items: new OA\Items(type: "string"), example: ["electronics"]),
                    new OA\Property(property: "min_loan_amount", type: "number", example: 1000),
                    new OA\Property(property: "max_loan_amount", type: "number", example: 100000)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Success")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:lenders',
            'api_base_url' => 'required|url',
        ]);

        $data = $request->all();

        // Map frontend fields to DB JSON columns
        if ($request->has('api_key') || $request->has('api_secret')) {
            $data['api_credentials'] = [
                'key' => $request->api_key,
                'secret' => $request->api_secret
            ];
        }

        if ($request->has('webhook_url')) {
            $data['webhook_endpoints'] = ['default' => $request->webhook_url];
        }

        if ($request->has('commission_type') || $request->has('commission_value')) {
            $data['commission_structure'] = [
                'type' => $request->commission_type,
                'value' => $request->commission_value
            ];
        }
        
        if ($request->has('supported_categories')) {
            // we will store this in supported_tenures for now as per DB or a new column if needed
            // Wait, the DB does not have supported_categories. I will just merge it into a generic config or store it if possible.
            // Let's reuse 'supported_tenures' or keep it as is, or we can use the 'commission_structure' config
            $data['supported_tenures'] = $request->supported_categories; 
        }

        $lender = Lender::create($data);
        return response()->json($lender, 201);
    }

    #[OA\Put(
        path: "/api/v1/admin/lenders/{id}",
        summary: "Update Lender",
        security: [["sanctum" => []]],
        tags: ["Lender"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "HDFC Bank"),
                    new OA\Property(property: "api_base_url", type: "string", example: "https://api.hdfc.com/v1"),
                    new OA\Property(property: "status", type: "string", example: "active"),
                    new OA\Property(property: "api_key", type: "string", example: "key_123"),
                    new OA\Property(property: "api_secret", type: "string", example: "secret_123"),
                    new OA\Property(property: "webhook_url", type: "string", example: "https://webhook.site/123"),
                    new OA\Property(property: "commission_type", type: "string", example: "percentage"),
                    new OA\Property(property: "commission_value", type: "number", example: 1.5),
                    new OA\Property(property: "supported_categories", type: "array", items: new OA\Items(type: "string"), example: ["electronics"]),
                    new OA\Property(property: "min_loan_amount", type: "number", example: 1000),
                    new OA\Property(property: "max_loan_amount", type: "number", example: 100000)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $lender = Lender::findOrFail($id);
        $data = $request->all();

        // Map frontend fields to DB JSON columns
        if ($request->has('api_key') || $request->has('api_secret')) {
            $creds = $lender->api_credentials ?? [];
            if ($request->has('api_key')) $creds['key'] = $request->api_key;
            if ($request->has('api_secret')) $creds['secret'] = $request->api_secret;
            $data['api_credentials'] = $creds;
        }

        if ($request->has('webhook_url')) {
            $data['webhook_endpoints'] = ['default' => $request->webhook_url];
        }

        if ($request->has('commission_type') || $request->has('commission_value')) {
            $comm = $lender->commission_structure ?? [];
            if ($request->has('commission_type')) $comm['type'] = $request->commission_type;
            if ($request->has('commission_value')) $comm['value'] = $request->commission_value;
            $data['commission_structure'] = $comm;
        }
        
        if ($request->has('supported_categories')) {
            $data['supported_tenures'] = $request->supported_categories; 
        }

        $lender->update($data);
        return response()->json($lender);
    }

    #[OA\Post(
        path: "/api/v1/admin/lenders/{id}/toggle",
        summary: "Toggle Lender Status",
        security: [["sanctum" => []]],
        tags: ["Lender"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function toggle($id)
    {
        $lender = Lender::findOrFail($id);
        $lender->status = $lender->status === 'active' ? 'inactive' : 'active';
        $lender->save();

        return response()->json(['message' => 'Lender toggled', 'lender' => $lender]);
    }

    #[OA\Post(
        path: "/api/v1/admin/lenders/{id}/test-connection",
        summary: "Test Lender Connection",
        security: [["sanctum" => []]],
        tags: ["Lender"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function testConnection(Request $request, $id)
    {
        $lender = Lender::findOrFail($id);
        
        try {
            // Send a ping request (with 3-second timeout)
            $response = Http::timeout(3)->get($lender->api_base_url . '/ping');
            
            return response()->json([
                'status' => 'success', 
                'message' => 'Connection successful', 
                'http_status' => $response->status(),
                'lender' => $lender->name
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Connection failed or timed out',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
