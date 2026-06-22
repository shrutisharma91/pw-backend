<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\LenderApiLog;
use App\Models\Lender;
use Illuminate\Http\Request;

class LenderSlaController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/lender-sla/metrics",
        summary: "Get SLA Metrics",
        security: [["sanctum" => []]],
        tags: ["LenderSla"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index()
    {
        // Mock aggregate metrics
        $metrics = Lender::all()->map(function ($lender) {
            return [
                'lender_id' => $lender->id,
                'name' => $lender->name,
                'p50_latency_ms' => rand(100, 300),
                'p95_latency_ms' => rand(300, 800),
                'p99_latency_ms' => rand(800, 2000),
                'approval_rate_percent' => rand(40, 85),
                'sla_breach' => rand(0, 1) == 1,
            ];
        });

        return response()->json($metrics);
    }

    #[OA\Get(
        path: "/api/v1/admin/lender-sla/export",
        summary: "Export SLA Metrics to CSV",
        security: [["sanctum" => []]],
        tags: ["LenderSla"],
        responses: [
            new OA\Response(response: 200, description: "CSV File Download")
        ]
    )]
    public function export()
    {
        $lenders = Lender::all();
        $csvData = "Lender ID,Lender Name,P50 Latency (ms),P95 Latency (ms),P99 Latency (ms),Approval Rate (%),SLA Breach\n";
        
        foreach ($lenders as $lender) {
            $p50 = rand(100, 300);
            $p95 = rand(300, 800);
            $p99 = rand(800, 2000);
            $approval = rand(40, 85);
            $breach = rand(0, 1) == 1 ? 'Yes' : 'No';
            $csvData .= "{$lender->id},\"{$lender->name}\",{$p50},{$p95},{$p99},{$approval},{$breach}\n";
        }

        return response()->make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="lender_sla_metrics.csv"',
        ]);
    }

    #[OA\Get(
        path: "/api/v1/admin/lender-sla/{id}/history",
        summary: "Get SLA History",
        security: [["sanctum" => []]],
        tags: ["LenderSla"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function history($id)
    {
        $lender = Lender::findOrFail($id);
        $history = [
            ['date' => '2024-10-01', 'latency' => 450, 'approval_rate' => 78.5],
            ['date' => '2024-10-02', 'latency' => 460, 'approval_rate' => 79.1],
            ['date' => '2024-10-03', 'latency' => 445, 'approval_rate' => 80.2],
        ];
        return response()->json(['lender' => $lender->name, 'history' => $history]);
    }

    #[OA\Get(
        path: "/api/v1/admin/lender-sla/{id}/breakdown",
        summary: "Get SLA Breakdown by API",
        security: [["sanctum" => []]],
        tags: ["LenderSla"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function breakdown($id)
    {
        $lender = Lender::findOrFail($id);
        $breakdown = [
            ['api_endpoint' => '/eligibility', 'avg_latency_ms' => 200, 'error_rate' => 0.5],
            ['api_endpoint' => '/create-loan', 'avg_latency_ms' => 800, 'error_rate' => 1.2],
            ['api_endpoint' => '/disbursal', 'avg_latency_ms' => 1500, 'error_rate' => 0.1],
        ];
        return response()->json(['lender' => $lender->name, 'breakdown' => $breakdown]);
    }

    #[OA\Get(
        path: "/api/v1/admin/lender-sla/trends",
        summary: "Get Global SLA Trends",
        security: [["sanctum" => []]],
        tags: ["LenderSla"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function trends()
    {
        $trends = [
            'overall_latency_trend' => 'improving',
            'overall_approval_rate' => 68.5,
            'top_performing_lender_id' => 1,
            'worst_performing_lender_id' => 3
        ];
        return response()->json(['trends' => $trends]);
    }
}
