<?php

namespace App\Http\Controllers;

use App\Models\EmiType;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EmiTypeController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/pricing/emi-types",
        summary: "Get all EMI Types",
        security: [["sanctum" => []]],
        tags: ["EMI Types"],
        responses: [
            new OA\Response(response: 200, description: "A list of EMI Types")
        ]
    )]
    public function index()
    {
        return response()->json(EmiType::all());
    }

    #[OA\Post(
        path: "/api/v1/admin/pricing/emi-types",
        summary: "Create EMI Type",
        security: [["sanctum" => []]],
        tags: ["EMI Types"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Standard No Cost EMI"),
                    new OA\Property(property: "type", type: "string", example: "no-cost"),
                    new OA\Property(property: "min_loan_amount", type: "number", example: 5000),
                    new OA\Property(property: "max_loan_amount", type: "number", example: 100000),
                    new OA\Property(property: "allowed_merchant_tiers", type: "array", items: new OA\Items(type: "string"), example: ["Gold", "Silver"]),
                    new OA\Property(property: "effective_from", type: "string", format: "date", example: "2024-01-01")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Success")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:no-cost,interest-bearing,deferred',
            'min_loan_amount' => 'nullable|numeric',
            'max_loan_amount' => 'nullable|numeric',
            'allowed_merchant_tiers' => 'nullable|array',
            'effective_from' => 'nullable|date',
        ]);

        $emiType = EmiType::create($validated);

        return response()->json($emiType, 201);
    }

    #[OA\Get(
        path: "/api/v1/admin/pricing/emi-types/{id}",
        summary: "Get EMI Type Details",
        security: [["sanctum" => []]],
        tags: ["EMI Types"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function show($id)
    {
        $emiType = EmiType::findOrFail($id);
        return response()->json($emiType);
    }

    #[OA\Put(
        path: "/api/v1/admin/pricing/emi-types/{id}",
        summary: "Update EMI Type",
        security: [["sanctum" => []]],
        tags: ["EMI Types"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Standard No Cost EMI Updated"),
                    new OA\Property(property: "type", type: "string", example: "no-cost"),
                    new OA\Property(property: "min_loan_amount", type: "number", example: 5000)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $emiType = EmiType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'type' => 'sometimes|in:no-cost,interest-bearing,deferred',
            'min_loan_amount' => 'nullable|numeric',
            'max_loan_amount' => 'nullable|numeric',
            'allowed_merchant_tiers' => 'nullable|array',
            'effective_from' => 'nullable|date',
        ]);

        $emiType->update($validated);

        return response()->json($emiType);
    }

    #[OA\Delete(
        path: "/api/v1/admin/pricing/emi-types/{id}",
        summary: "Delete EMI Type",
        security: [["sanctum" => []]],
        tags: ["EMI Types"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted")
        ]
    )]
    public function destroy($id)
    {
        $emiType = EmiType::findOrFail($id);
        $emiType->delete();

        return response()->json(null, 204);
    }
}
