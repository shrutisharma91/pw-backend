<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VerificationLog;

class VerificationLogController extends Controller
{
    public function index(Request $request, $merchantId)
    {
        // Screen 18 - Verification API Logs
        $logs = VerificationLog::where('merchant_id', $merchantId);
        
        if ($request->has('api_type')) {
            $logs->where('api_type', $request->api_type);
        }
        
        return response()->json($logs->latest()->paginate(15));
    }
}
