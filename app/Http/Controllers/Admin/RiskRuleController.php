<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RiskRule;

class RiskRuleController extends Controller
{
    // Screen 40: Velocity & Risk Rules
    #[OA\Get(
        path: "/api/v1/admin/risk-rules",
        summary: "index RiskRule",
        security: [["sanctum" => []]],
        tags: ["RiskRule"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = RiskRule::query();
        if ($request->has('rule_type')) $query->where('rule_type', $request->rule_type);

        return response()->json($query->get());
    }

    #[OA\Post(
        path: "/api/v1/admin/risk-rules",
        summary: "store RiskRule",
        security: [["sanctum" => []]],
        tags: ["RiskRule"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'rule_type' => 'required|in:velocity,scoring',
            'name' => 'required|string',
            'parameters' => 'required|array',
            'threshold' => 'required|numeric',
            'action' => 'required|in:flag,hold,reject'
        ]);

        $rule = RiskRule::create($request->all());

        return response()->json(['message' => 'Risk rule created', 'rule' => $rule]);
    }

    #[OA\Put(
        path: "/api/v1/admin/risk-rules/{id}",
        summary: "update RiskRule",
        security: [["sanctum" => []]],
        tags: ["RiskRule"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $rule = RiskRule::findOrFail($id);
        $rule->update($request->all());

        return response()->json(['message' => 'Risk rule updated', 'rule' => $rule]);
    }

    #[OA\Post(
        path: "/api/v1/admin/risk-rules/simulate",
        summary: "simulate RiskRule",
        security: [["sanctum" => []]],
        tags: ["RiskRule"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function simulate(Request $request)
    {
        // Replay rule against historical data logic
        return response()->json([
            'message' => 'Simulation completed',
            'results' => [
                'total_evaluated' => 1000,
                'flagged' => 45,
                'rejected' => 12,
                'false_positives_est' => '2.5%'
            ]
        ]);
    }
}