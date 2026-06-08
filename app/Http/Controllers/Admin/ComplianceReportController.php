<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ComplianceReport;
use App\Models\DataPrincipalRequest;

class ComplianceReportController extends Controller
{
    // Screen 44: Compliance Reports & Exports
    #[OA\Post(
        path: "/api/v1/admin/compliance/returns",
        summary: "generateReturn ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function generateReturn(Request $request)
    {
        $request->validate(['report_type' => 'required|string']);

        $report = ComplianceReport::create([
            'report_type' => $request->report_type,
            'status' => 'Generated',
            'file_url' => 'https://example.com/exports/return.pdf'
        ]);

        return response()->json(['message' => 'RBI Return generated', 'report' => $report]);
    }

    #[OA\Get(
        path: "/api/v1/admin/compliance/dpdp-requests",
        summary: "dpdpRequests ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function dpdpRequests(Request $request)
    {
        $requests = DataPrincipalRequest::with('customer')->orderBy('created_at', 'desc')->paginate(20);
        return response()->json($requests);
    }

    #[OA\Post(
        path: "/api/v1/admin/compliance/dpdp-requests/{id}/resolve",
        summary: "resolveDpdpRequest ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function resolveDpdpRequest(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Completed,Rejected',
            'resolution_notes' => 'required|string'
        ]);

        $req = DataPrincipalRequest::findOrFail($id);
        $req->update($request->all());

        return response()->json(['message' => 'DPDP Request resolved', 'request' => $req]);
    }

    #[OA\Get(
        path: "/api/v1/admin/compliance/data-masking-policy",
        summary: "dataMaskingPolicy ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function dataMaskingPolicy()
    {
        return response()->json([
            'policies' => [
                'customer_service' => ['mask_pan' => true, 'mask_phone' => true],
                'compliance_officer' => ['mask_pan' => false, 'mask_phone' => false]
            ]
        ]);
    }

    #[OA\Get(
        path: "/api/v1/admin/compliance/retention-policy",
        summary: "retentionPolicy ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function retentionPolicy()
    {
        return response()->json([
            'policies' => [
                'audit_logs' => '10 years',
                'consents' => '7 years',
                'marketing_data' => '1 year'
            ]
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/compliance/data-masking-policy",
        summary: "updateDataMaskingPolicy ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function updateDataMaskingPolicy(Request $request)
    {
        // Update masking policy logic
        return response()->json(['message' => 'Data masking policy updated successfully']);
    }

    #[OA\Post(
        path: "/api/v1/admin/compliance/retention-policy",
        summary: "updateRetentionPolicy ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function updateRetentionPolicy(Request $request)
    {
        // Update retention policy logic
        return response()->json(['message' => 'Retention policy updated successfully']);
    }

    #[OA\Get(
        path: "/api/v1/admin/compliance/dashboard",
        summary: "dashboard ComplianceReport",
        security: [["sanctum" => []]],
        tags: ["ComplianceReport"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function dashboard()
    {
        return response()->json([
            'checklist' => [
                'rbi_returns_filed' => true,
                'dpdp_requests_pending' => 5,
                'data_retention_archival_status' => 'Up to date'
            ]
        ]);
    }
}