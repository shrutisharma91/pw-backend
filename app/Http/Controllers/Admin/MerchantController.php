<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Merchant;

class MerchantController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/merchants",
        summary: "Get Merchant Directory",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "region", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "category", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "sales_exec_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "signup_date", in: "query", required: false, schema: new OA\Schema(type: "string", format: "date"))
        ],
        responses: [
            new OA\Response(response: 200, description: "List of merchants")
        ]
    )]
    public function index(Request $request)
    {
        $merchants = Merchant::query();
        
        if ($request->has('status')) $merchants->where('status', $request->status);
        if ($request->has('region')) $merchants->where('region', $request->region);
        if ($request->has('category')) $merchants->where('category', $request->category);
        if ($request->has('sales_exec_id')) $merchants->where('sales_exec_id', $request->sales_exec_id);
        if ($request->has('signup_date')) $merchants->whereDate('created_at', clone new \Carbon\Carbon($request->signup_date));
        
        if ($request->has('nopaginate')) {
            return response()->json($merchants->get());
        }
        
        return response()->json($merchants->paginate(15));
    }

    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}",
        summary: "Get Merchant Profile",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Merchant 360 Profile")
        ]
    )]
    public function show($id)
    {
        $merchant = Merchant::findOrFail($id);
        return response()->json($merchant);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants",
        summary: "Create Merchant",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "business_name", type: "string", example: "Acme Corp"),
                    new OA\Property(property: "region", type: "string", example: "North"),
                    new OA\Property(property: "category", type: "string", example: "Electronics")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Merchant Created")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'region' => 'nullable|string',
            'category' => 'nullable|string',
        ]);

        $merchant = Merchant::create([
            'business_name' => $request->business_name,
            'region' => $request->region,
            'category' => $request->category,
            'status' => 'Draft'
        ]);

        return response()->json(['message' => 'Merchant created successfully', 'merchant' => $merchant], 201);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/approve",
        summary: "Approve Merchant",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "comment", type: "string", example: "All documents verified.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function approve(Request $request, $id)
    {
        $request->validate(['comment' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Approved';
        $merchant->save();
        
        return response()->json(['message' => 'Merchant approved', 'merchant' => $merchant]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/reject",
        summary: "Reject Merchant",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "Invalid GST number.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function reject(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Rejected';
        $merchant->save();
        
        return response()->json(['message' => 'Merchant rejected', 'merchant' => $merchant]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/bulk-approve",
        summary: "Bulk Approve Merchants",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "merchant_ids", type: "array", items: new OA\Items(type: "integer")),
                    new OA\Property(property: "comment", type: "string", example: "Bulk approved.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'merchant_ids' => 'required|array',
            'comment' => 'required|string'
        ]);
        Merchant::whereIn('id', $request->merchant_ids)->update(['status' => 'Approved']);
        return response()->json(['message' => 'Bulk approval successful']);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/bulk-reject",
        summary: "Bulk Reject Merchants",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "merchant_ids", type: "array", items: new OA\Items(type: "integer")),
                    new OA\Property(property: "reason", type: "string", example: "Fraudulent applications.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function bulkReject(Request $request)
    {
        $request->validate([
            'merchant_ids' => 'required|array',
            'reason' => 'required|string'
        ]);
        Merchant::whereIn('id', $request->merchant_ids)->update(['status' => 'Rejected']);
        return response()->json(['message' => 'Bulk rejection successful']);
    }

    #[OA\Get(
        path: "/api/v1/admin/merchants/export",
        summary: "Export Merchants CSV",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        responses: [
            new OA\Response(response: 200, description: "CSV Data")
        ]
    )]
    public function export(Request $request)
    {
        // Actual Export Implementation
        $merchants = Merchant::all();
        $csvData = "ID,Business Name,Status,Region,Category\n";
        foreach ($merchants as $m) {
            $csvData .= "{$m->id},\"{$m->business_name}\",{$m->status},{$m->region},{$m->category}\n";
        }
        return response()->make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="merchants.csv"',
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/re-kyc",
        summary: "Trigger Re-KYC",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "Documents expired.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function reKyc(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Re-KYC';
        $merchant->save();
        
        return response()->json(['message' => 'Re-KYC triggered', 'merchant' => $merchant]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/bulk-re-kyc",
        summary: "Bulk Trigger Re-KYC",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "merchant_ids", type: "array", items: new OA\Items(type: "integer")),
                    new OA\Property(property: "reason", type: "string", example: "Compliance update.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function bulkReKyc(Request $request)
    {
        $request->validate([
            'merchant_ids' => 'required|array',
            'reason' => 'required|string'
        ]);
        Merchant::whereIn('id', $request->merchant_ids)->update(['status' => 'Re-KYC']);
        return response()->json(['message' => 'Bulk Re-KYC triggered']);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/suspend",
        summary: "Suspend Merchant",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason_code", type: "string", example: "FRAUD_DETECTED")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function suspend(Request $request, $id)
    {
        $request->validate(['reason_code' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Suspended';
        $merchant->suspension_reason = $request->reason_code;
        $merchant->save();
        
        return response()->json(['message' => 'Merchant suspended', 'merchant' => $merchant]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/send-notice",
        summary: "Send Notice to Merchant",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "notice_text", type: "string", example: "Please update your bank details.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function sendNotice(Request $request, $id)
    {
        $request->validate(['notice_text' => 'required|string']);
        
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Notice Sent';
        $merchant->save();
        
        // Mock Implementation: create an entry in db if we had a Notice table
        return response()->json(['message' => 'Notice sent successfully', 'merchant' => $merchant]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/escalate",
        summary: "Escalate Merchant to Risk",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "escalation_reason", type: "string", example: "High NPA rate observed.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function escalateToRisk(Request $request, $id)
    {
        $request->validate(['escalation_reason' => 'required|string']);
        
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Escalated';
        $merchant->save();
        
        // Mock Implementation
        return response()->json(['message' => 'Escalated to Risk team', 'merchant' => $merchant]);
    }

    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}/documents",
        summary: "List Merchant Documents",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function documents($id)
    {
        $documents = \Illuminate\Support\Facades\DB::table('documents')
            ->where('entity_type', 'merchant')
            ->where('entity_id', $id)
            ->get();
        return response()->json($documents);
    }

    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}/notes",
        summary: "List Merchant Notes",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function notes($id)
    {
        $notes = \Illuminate\Support\Facades\DB::table('merchant_notes')
            ->where('merchant_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($notes);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/notes",
        summary: "Add Merchant Note",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "note", type: "string", example: "Called merchant regarding KYC.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Success")
        ]
    )]
    public function addNote(Request $request, $id)
    {
        $request->validate(['note' => 'required|string']);
        \Illuminate\Support\Facades\DB::table('merchant_notes')->insert([
            'merchant_id' => $id,
            'user_id' => \Illuminate\Support\Facades\Auth::id() ?? 1,
            'note' => $request->note,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        return response()->json(['message' => 'Note added successfully'], 201);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/approve-changes",
        summary: "Approve Profile Changes",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function approveChanges(Request $request, $id)
    {
        // Mock implementation for approving profile modifications
        return response()->json(['message' => 'Profile changes approved for merchant ' . $id]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/reactivate",
        summary: "Reactivate Merchant",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function reactivate(Request $request, $id)
    {
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Active';
        $merchant->suspension_reason = null;
        $merchant->save();
        
        return response()->json(['message' => 'Merchant reactivated successfully', 'merchant' => $merchant]);
    }

    #[OA\Get(
        path: "/api/v1/admin/merchants/{id}/documents/{document_id}/view",
        summary: "Document Viewer Panel",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "document_id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function documentViewer($id, $document_id)
    {
        $document = \Illuminate\Support\Facades\DB::table('documents')
            ->where('entity_type', 'merchant')
            ->where('entity_id', $id)
            ->where('id', $document_id)
            ->first();
            
        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        return response()->json([
            'document' => $document,
            'viewer_url' => 'https://mock-viewer.finz.com/view/' . $document_id,
            'status' => 'ready'
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/merchants/{id}/ephemeral-notes",
        summary: "Internal Notes Thread (browser-only, not persisted)",
        security: [["sanctum" => []]],
        tags: ["Merchant"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "note", type: "string", example: "Draft note.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function ephemeralNotes(Request $request, $id)
    {
        return response()->json([
            'message' => 'Note received but not persisted.',
            'echo' => $request->note
        ]);
    }
}
