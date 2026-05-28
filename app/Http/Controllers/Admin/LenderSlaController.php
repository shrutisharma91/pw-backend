<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LenderApiLog;
use App\Models\Lender;
use Illuminate\Http\Request;

class LenderSlaController extends Controller
{
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

    public function export()
    {
        // Return CSV data or a download link
        return response()->json(['message' => 'Export triggered. You will receive an email shortly.']);
    }
}
