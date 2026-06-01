<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\LenderRule;
use Illuminate\Http\Request;

class LenderRuleController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/lender-rules",
        summary: "List Lender Rules",
        security: [["sanctum" => []]],
        tags: ["LenderRule"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index()
    {
        $rules = LenderRule::with('lender')->get();
        return response()->json($rules);
    }

    #[OA\Post(
        path: "/api/v1/admin/lender-rules",
        summary: "Create Lender Rule",
        security: [["sanctum" => []]],
        tags: ["LenderRule"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Reject Low Credit"),
                    new OA\Property(property: "lender_id", type: "integer", example: 1),
                    new OA\Property(property: "conditions", type: "object", example: ["credit_score" => ["<" => 600]]),
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
            'name' => 'required|string',
            'conditions' => 'required|array',
            'lender_id' => 'required|exists:lenders,id',
        ]);

        $rule = LenderRule::create($request->all());
        return response()->json($rule, 201);
    }

    #[OA\Put(
        path: "/api/v1/admin/lender-rules/{id}",
        summary: "Update Lender Rule",
        security: [["sanctum" => []]],
        tags: ["LenderRule"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Reject Low Credit"),
                    new OA\Property(property: "lender_id", type: "integer", example: 1),
                    new OA\Property(property: "conditions", type: "object", example: ["credit_score" => ["<" => 650]]),
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
        $rule = LenderRule::findOrFail($id);
        $rule->update($request->all());
        return response()->json($rule);
    }

    #[OA\Post(
        path: "/api/v1/admin/lender-rules/{id}/archive",
        summary: "Archive Lender Rule",
        security: [["sanctum" => []]],
        tags: ["LenderRule"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function archive($id)
    {
        $rule = LenderRule::findOrFail($id);
        $rule->status = 'archived';
        $rule->save();
        
        return response()->json(['message' => 'Rule archived', 'rule' => $rule]);
    }
}
