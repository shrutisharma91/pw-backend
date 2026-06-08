<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoanApplication;
use App\Models\LoanTimelineEvent;
use App\Models\LoanDocument;
use App\Models\LoanCommunication;
use App\Models\SavedFilter;
use Illuminate\Support\Facades\Auth;

class LoanApplicationController extends Controller
{
    // Screen 33: Monitor and Export
    #[OA\Get(
        path: "/api/v1/admin/loans",
        summary: "index LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = LoanApplication::with(['customer', 'merchant', 'store', 'lender']);

        if ($request->has('merchant_id')) $query->where('merchant_id', $request->merchant_id);
        if ($request->has('store_id')) $query->where('store_id', $request->store_id);
        if ($request->has('lender_id')) $query->where('lender_id', $request->lender_id);
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('date')) $query->whereDate('created_at', $request->date);
        if ($request->has('min_amount')) $query->where('amount', '>=', $request->min_amount);
        if ($request->has('max_amount')) $query->where('amount', '<=', $request->max_amount);
        
        // Stuck application badge
        if ($request->has('stuck')) $query->where('sla_breached', true);

        return response()->json($query->paginate(20));
    }

    #[OA\Get(
        path: "/api/v1/admin/loans/export",
        summary: "export LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function export(Request $request)
    {
        // Dummy export logic
        return response()->json(['message' => 'Export initiated. Link will be sent to email.']);
    }

    #[OA\Get(
        path: "/api/v1/admin/loans/saved-filters",
        summary: "getSavedFilters LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function getSavedFilters(Request $request)
    {
        $filters = SavedFilter::where('user_id', Auth::id())->get();
        return response()->json($filters);
    }

    #[OA\Post(
        path: "/api/v1/admin/loans/saved-filters",
        summary: "saveFilter LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function saveFilter(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'filter_payload' => 'required|array'
        ]);

        $filter = SavedFilter::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'filter_payload' => $request->filter_payload
        ]);

        return response()->json(['message' => 'Filter saved', 'filter' => $filter]);
    }

    // Screen 34: Detail, Timeline, Documents
    #[OA\Get(
        path: "/api/v1/admin/loans/{id}",
        summary: "show LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function show($id)
    {
        $loan = LoanApplication::with(['customer', 'merchant', 'store', 'lender'])->findOrFail($id);
        return response()->json($loan);
    }

    #[OA\Get(
        path: "/api/v1/admin/loans/{id}/timeline",
        summary: "timeline LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function timeline($id)
    {
        $events = LoanTimelineEvent::where('loan_application_id', $id)->orderBy('created_at', 'desc')->get();
        return response()->json($events);
    }

    #[OA\Get(
        path: "/api/v1/admin/loans/{id}/documents",
        summary: "documents LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function documents($id)
    {
        $documents = LoanDocument::where('loan_application_id', $id)->get();
        return response()->json($documents);
    }

    #[OA\Get(
        path: "/api/v1/admin/loans/{id}/communications",
        summary: "communications LoanApplication",
        security: [["sanctum" => []]],
        tags: ["LoanApplication"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function communications($id)
    {
        $communications = LoanCommunication::where('loan_application_id', $id)->orderBy('created_at', 'desc')->get();
        return response()->json($communications);
    }
}