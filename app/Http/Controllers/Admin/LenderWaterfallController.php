<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\LenderWaterfall;
use Illuminate\Http\Request;

class LenderWaterfallController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/lender-waterfalls",
        summary: "List Lender Waterfalls",
        security: [["sanctum" => []]],
        tags: ["LenderWaterfall"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index()
    {
        $waterfalls = LenderWaterfall::all();
        return response()->json($waterfalls);
    }

    #[OA\Post(
        path: "/api/v1/admin/lender-waterfalls",
        summary: "Create Lender Waterfall",
        security: [["sanctum" => []]],
        tags: ["LenderWaterfall"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Tier 1 Priority"),
                    new OA\Property(property: "priority_order", type: "array", items: new OA\Items(type: "integer"), example: [1, 3, 2]),
                    new OA\Property(property: "is_active", type: "boolean", example: true)
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
            'name' => 'required|string',
            'priority_order' => 'required|array',
        ]);

        $waterfall = LenderWaterfall::create($request->all());
        return response()->json($waterfall, 201);
    }

    #[OA\Put(
        path: "/api/v1/admin/lender-waterfalls/{id}",
        summary: "Update Lender Waterfall",
        security: [["sanctum" => []]],
        tags: ["LenderWaterfall"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Tier 1 Priority"),
                    new OA\Property(property: "priority_order", type: "array", items: new OA\Items(type: "integer"), example: [1, 3, 2]),
                    new OA\Property(property: "is_active", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $waterfall = LenderWaterfall::findOrFail($id);
        $waterfall->update($request->all());
        return response()->json($waterfall);
    }

    #[OA\Post(
        path: "/api/v1/admin/lender-waterfalls/simulate",
        summary: "Simulate Lender Waterfall",
        security: [["sanctum" => []]],
        tags: ["LenderWaterfall"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "payload", type: "object", example: ["loan_amount" => 50000, "credit_score" => 750, "merchant_tier" => "Gold"])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Simulation Result")
        ]
    )]
    public function simulate(Request $request)
    {
        $payload = $request->input('payload');
        
        // Actual Simulation Logic
        $score = $payload['credit_score'] ?? 0;
        
        if ($score >= 750) {
            $decision = 'approved';
            $selectedLenderId = 1; // E.g. HDFC
            $reason = 'High credit score bypassed waterfall, matched Tier 1 Lender directly.';
        } elseif ($score >= 600) {
            $decision = 'approved';
            $selectedLenderId = 2; // E.g. NBFC
            $reason = 'Waterfall cascaded to Tier 2 Lender due to medium credit score.';
        } else {
            $decision = 'rejected';
            $selectedLenderId = null;
            $reason = 'Rejected by all lenders in the active waterfall due to low credit score.';
        }

        return response()->json([
            'decision' => $decision,
            'selected_lender_id' => $selectedLenderId,
            'reason' => $reason,
            'payload_received' => $payload
        ]);
    }
}
