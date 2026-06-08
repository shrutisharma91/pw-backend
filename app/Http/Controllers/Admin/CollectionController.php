<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Collection;
use App\Models\BounceEvent;

class CollectionController extends Controller
{
    // Screen 37: Collections & Bounce
    #[OA\Get(
        path: "/api/v1/admin/collections",
        summary: "index Collection",
        security: [["sanctum" => []]],
        tags: ["Collection"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = Collection::with(['loanApplication', 'agent']);

        if ($request->has('dpd_bucket')) $query->where('dpd_bucket', $request->dpd_bucket);
        if ($request->has('region')) {
            // Pseudo logic for region
        }

        return response()->json($query->paginate(20));
    }

    #[OA\Post(
        path: "/api/v1/admin/collections/{id}/assign-agent",
        summary: "assignAgent Collection",
        security: [["sanctum" => []]],
        tags: ["Collection"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function assignAgent(Request $request, $id)
    {
        $request->validate(['agent_id' => 'required|exists:users,id']);

        $collection = Collection::findOrFail($id);
        $collection->agent_id = $request->agent_id;
        $collection->save();

        return response()->json(['message' => 'Agent assigned successfully', 'collection' => $collection]);
    }

    #[OA\Get(
        path: "/api/v1/admin/collections/bounces",
        summary: "bounceFeed Collection",
        security: [["sanctum" => []]],
        tags: ["Collection"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function bounceFeed(Request $request)
    {
        $bounces = BounceEvent::with('collection')->orderBy('date', 'desc')->paginate(20);
        return response()->json($bounces);
    }

    #[OA\Post(
        path: "/api/v1/admin/collections/bounces/{id}/retry",
        summary: "retryBounce Collection",
        security: [["sanctum" => []]],
        tags: ["Collection"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function retryBounce($id)
    {
        $bounce = BounceEvent::findOrFail($id);
        $bounce->auto_retry_status = 'Initiated';
        $bounce->save();

        return response()->json(['message' => 'Auto-retry initiated', 'bounce' => $bounce]);
    }

    #[OA\Post(
        path: "/api/v1/admin/collections/{id}/npa-status",
        summary: "setNpaStatus Collection",
        security: [["sanctum" => []]],
        tags: ["Collection"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function setNpaStatus(Request $request, $id)
    {
        $request->validate(['npa_status' => 'required|in:Foreclosure,Settled,NOC Generated,Written-off']);

        $collection = Collection::findOrFail($id);
        $collection->npa_status = $request->npa_status;
        $collection->status = 'Closed';
        $collection->save();

        return response()->json(['message' => 'NPA Status updated', 'collection' => $collection]);
    }
}