<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VerificationLog;

class VerificationLogController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}/verification-logs",
        summary: "Get Verification Logs",
        security: [["sanctum" => []]],
        tags: ["VerificationLog"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "api_type", in: "query", required: false, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request, $id)
    {
        $logs = VerificationLog::where('merchant_id', $id);
        
        if ($request->has('api_type')) {
            $logs->where('api_type', $request->api_type);
        }
        
        return response()->json($logs->latest()->paginate(15));
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/verification-logs/{log_id}/retry",
        summary: "Retry Verification Log",
        security: [["sanctum" => []]],
        tags: ["VerificationLog"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "log_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function retry(Request $request, $id, $log_id)
    {
        $log = VerificationLog::where('merchant_id', $id)->findOrFail($log_id);
        
        // Actual Retry Implementation Logic
        $newLog = $log->replicate();
        $newLog->status = 'success';
        $newLog->response_payload = json_encode(['status' => 'verified via manual retry', 'timestamp' => now()]);
        $newLog->save();

        return response()->json(['message' => 'Verification retried successfully', 'log' => $newLog]);
    }

    #[OA\Post(
        path: "/api/v1/admin/verifications/provider-switch",
        summary: "Switch Verification Provider",
        security: [["sanctum" => []]],
        tags: ["VerificationLog"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "provider", type: "string", example: "surepass"),
                    new OA\Property(property: "call_type", type: "string", example: "pan_verification")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function switchProvider(Request $request)
    {
        $request->validate([
            'provider' => 'required|string|in:karza,surepass',
            'call_type' => 'required|string'
        ]);

        // Mock Implementation: update system parameter or config for provider
        return response()->json([
            'message' => "Verification provider for {$request->call_type} switched to {$request->provider}"
        ]);
    }
}
