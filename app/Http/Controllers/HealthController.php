<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Get application health status
     */
    public function check(): JsonResponse
    {
        try {
            // Check database connection
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
                'database' => 'connected',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toIso8601String(),
                'error' => 'Database connection failed',
            ], 503);
        }
    }
}
