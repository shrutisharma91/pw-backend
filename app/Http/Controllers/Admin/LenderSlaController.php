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
}
