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
                    new OA\Property(property: "status", type: "string", example: "active")
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

        $lender = Lender::create($request->all());
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
                    new OA\Property(property: "status", type: "string", example: "active")
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
        $lender->update($request->all());
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
