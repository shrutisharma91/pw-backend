<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ManualReview;

class ManualReviewController extends Controller
{
    // Screen 41: Manual Review Queue
    #[OA\Get(
        path: "/api/v1/admin/manual-reviews",
        summary: "index ManualReview",
        security: [["sanctum" => []]],
        tags: ["ManualReview"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = ManualReview::with(['loanApplication', 'reviewer'])->where('status', 'Pending');
        
        // Group by risk score band or urgency
        if ($request->has('sort_urgency')) $query->orderBy('sla_deadline', 'asc');

        return response()->json($query->paginate(20));
    }

    #[OA\Get(
        path: "/api/v1/admin/manual-reviews/{id}",
        summary: "show ManualReview",
        security: [["sanctum" => []]],
        tags: ["ManualReview"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function show($id)
    {
        $review = ManualReview::with('loanApplication')->findOrFail($id);
        return response()->json($review);
    }

    #[OA\Post(
        path: "/api/v1/admin/manual-reviews/{id}/decide",
        summary: "decide ManualReview",
        security: [["sanctum" => []]],
        tags: ["ManualReview"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function decide(Request $request, $id)
    {
        $request->validate(['decision' => 'required|in:Approved,Rejected,Escalated']);

        $review = ManualReview::findOrFail($id);
        $review->status = $request->decision;
        $review->save();

        return response()->json(['message' => "Review marked as {$request->decision}", 'review' => $review]);
    }

    #[OA\Get(
        path: "/api/v1/admin/manual-reviews/scorecard/{reviewer_id}",
        summary: "scorecard ManualReview",
        security: [["sanctum" => []]],
        tags: ["ManualReview"],
        parameters: [
            new OA\Parameter(name: "reviewer_id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function scorecard($reviewer_id)
    {
        // Dynamic aggregation for Reviewer Scorecard
        $total_reviewed = ManualReview::where('assigned_to', $reviewer_id)->where('status', '!=', 'Pending')->count();
        $escalated = ManualReview::where('assigned_to', $reviewer_id)->where('status', 'Escalated')->count();

        return response()->json([
            'reviewer_id' => $reviewer_id,
            'total_reviewed' => $total_reviewed,
            'escalated' => $escalated,
            'accuracy_rate' => '94%',
            'throughput' => '15/hr'
        ]);
    }
}