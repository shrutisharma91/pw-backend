<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FraudAlert;

class FraudAlertController extends Controller
{
    // Screen 38: Fraud Alert Feed
    #[OA\Get(
        path: "/api/v1/admin/fraud-alerts",
        summary: "index FraudAlert",
        security: [["sanctum" => []]],
        tags: ["FraudAlert"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = FraudAlert::with(['customer', 'merchant'])->orderBy('created_at', 'desc');

        if ($request->has('signal_type')) $query->where('signal_type', $request->signal_type);
        if ($request->has('severity')) $query->where('severity', $request->severity);
        if ($request->has('status')) $query->where('status', $request->status);

        return response()->json($query->paginate(20));
    }

    #[OA\Post(
        path: "/api/v1/admin/fraud-alerts/{id}/block",
        summary: "block FraudAlert",
        security: [["sanctum" => []]],
        tags: ["FraudAlert"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function block($id)
    {
        $alert = FraudAlert::findOrFail($id);
        $alert->status = 'Blocked';
        $alert->save();

        return response()->json(['message' => 'Alert source blocked successfully', 'alert' => $alert]);
    }

    #[OA\Post(
        path: "/api/v1/admin/fraud-alerts/{id}/unblock",
        summary: "unblock FraudAlert",
        security: [["sanctum" => []]],
        tags: ["FraudAlert"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function unblock($id)
    {
        $alert = FraudAlert::findOrFail($id);
        $alert->status = 'Resolved'; // Unblocked
        $alert->save();

        return response()->json(['message' => 'Alert source unblocked successfully', 'alert' => $alert]);
    }

    #[OA\Post(
        path: "/api/v1/admin/fraud-alerts/{id}/escalate",
        summary: "escalate FraudAlert",
        security: [["sanctum" => []]],
        tags: ["FraudAlert"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function escalate($id)
    {
        $alert = FraudAlert::findOrFail($id);
        $alert->status = 'Escalated';
        $alert->save();

        return response()->json(['message' => 'Alert escalated successfully', 'alert' => $alert]);
    }

    #[OA\Get(
        path: "/api/v1/admin/fraud-alerts/stats/heatmap",
        summary: "heatmap FraudAlert",
        security: [["sanctum" => []]],
        tags: ["FraudAlert"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function heatmap()
    {
        // Dummy data for Heatmap plotting
        $data = [
            ['region' => 'Delhi', 'count' => 120, 'severity' => 'High'],
            ['region' => 'Mumbai', 'count' => 85, 'severity' => 'Medium'],
            ['region' => 'Bangalore', 'count' => 45, 'severity' => 'Low']
        ];
        return response()->json($data);
    }
}