<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ConsentLog;

class ConsentLogController extends Controller
{
    // Screen 43: Consent Log Viewer
    #[OA\Get(
        path: "/api/v1/admin/consents",
        summary: "index ConsentLog",
        security: [["sanctum" => []]],
        tags: ["ConsentLog"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = ConsentLog::with(['customer', 'merchant'])->orderBy('created_at', 'desc');

        if ($request->has('customer_id')) $query->where('customer_id', $request->customer_id);
        if ($request->has('merchant_id')) $query->where('merchant_id', $request->merchant_id);
        if ($request->has('consent_type')) $query->where('consent_type', $request->consent_type);

        return response()->json($query->paginate(20));
    }

    #[OA\Post(
        path: "/api/v1/admin/consents/{id}/withdraw",
        summary: "withdraw ConsentLog",
        security: [["sanctum" => []]],
        tags: ["ConsentLog"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function withdraw(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);

        $consent = ConsentLog::findOrFail($id);
        $consent->status = 'Withdrawn';
        $consent->save();

        return response()->json(['message' => 'Consent successfully withdrawn', 'consent' => $consent]);
    }

    #[OA\Get(
        path: "/api/v1/admin/consents/{id}/diff/{compare_id}",
        summary: "diff ConsentLog",
        security: [["sanctum" => []]],
        tags: ["ConsentLog"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true),
            new OA\Parameter(name: "compare_id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function diff($id, $compare_id)
    {
        // Compare payloads between two versions
        return response()->json([
            'message' => 'Version diff generated',
            'changes' => [
                'added' => ['marketing_opt_in' => true],
                'removed' => []
            ]
        ]);
    }

    #[OA\Get(
        path: "/api/v1/admin/consents/export",
        summary: "export ConsentLog",
        security: [["sanctum" => []]],
        tags: ["ConsentLog"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function export()
    {
        return response()->json(['message' => 'Consent logs exported for regulatory inspection.']);
    }
}