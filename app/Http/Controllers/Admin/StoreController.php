<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/stores",
        summary: "Get Store Directory",
        security: [["sanctum" => []]],
        tags: ["Store"],
        parameters: [
            new OA\Parameter(name: "cluster", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "region", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $stores = Store::with('merchant');
        
        if ($request->has('status')) $stores->where('status', $request->status);
        if ($request->has('region')) $stores->where('region', $request->region);
        
        return response()->json($stores->paginate(15));
    }

    #[OA\Get(
        path: "/api/v1/admin/stores/{id}",
        summary: "Get Store Details",
        security: [["sanctum" => []]],
        tags: ["Store"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function show($id)
    {
        $store = Store::with(['merchant', 'products' => function($q) {
            $q->withPivot('stock_quantity');
        }])->findOrFail($id);

        $store->mock_recent_loans = [
            'last_30_days' => rand(5, 20),
            'last_90_days' => rand(15, 60),
        ];

        return response()->json($store);
    }

    #[OA\Post(
        path: "/api/v1/admin/stores/{id}/deactivate",
        summary: "Deactivate Store",
        security: [["sanctum" => []]],
        tags: ["Store"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "Store closure")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function deactivate(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $store = Store::findOrFail($id);
        $store->status = 'deactivated';
        $store->deactivation_reason = $request->reason;
        $store->save();

        return response()->json(['message' => 'Store deactivated successfully', 'store' => $store]);
    }

    #[OA\Get(
        path: "/api/v1/admin/stores/export",
        summary: "Export Stores CSV",
        security: [["sanctum" => []]],
        tags: ["Store"],
        responses: [
            new OA\Response(response: 200, description: "CSV file")
        ]
    )]
    public function export(Request $request)
    {
        $stores = Store::with('merchant')->get();
        $csvData = "ID,Store Name,Merchant,Status,Region\n";
        foreach ($stores as $s) {
            $merchantName = $s->merchant ? $s->merchant->business_name : 'N/A';
            $csvData .= "{$s->id},\"{$s->name}\",\"{$merchantName}\",{$s->status},{$s->region}\n";
        }
        return response()->make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stores.csv"',
        ]);
    }
}
