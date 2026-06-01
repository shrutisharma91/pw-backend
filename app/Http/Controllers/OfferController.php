<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;
use App\Models\Offer;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/offers",
        summary: "List Offers",
        security: [["sanctum" => []]],
        tags: ["Offer"],
        parameters: [
            new OA\Parameter(name: "status", in: "query", required: false, schema: new OA\Schema(type: "string")),
            new OA\Parameter(name: "merchant_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = Offer::query();
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        return response()->json($query->get());
    }

    #[OA\Post(
        path: "/api/v1/admin/offers",
        summary: "Create Offer",
        security: [["sanctum" => []]],
        tags: ["Offer"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", example: "Diwali Special Cashback"),
                    new OA\Property(property: "description", type: "string", example: "Get 5% cashback up to ₹1000"),
                    new OA\Property(property: "offer_type", type: "string", example: "cashback"),
                    new OA\Property(property: "discount_value", type: "number", example: 5.0),
                    new OA\Property(property: "scope_type", type: "string", example: "platform"),
                    new OA\Property(property: "start_date", type: "string", format: "date-time", example: "2024-10-01 00:00:00"),
                    new OA\Property(property: "end_date", type: "string", format: "date-time", example: "2024-10-31 23:59:59"),
                    new OA\Property(property: "budget_cap", type: "number", example: 500000),
                    new OA\Property(property: "auto_pause", type: "boolean", example: true),
                    new OA\Property(property: "is_platform_offer", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Success")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'offer_type' => 'required|in:flat,percentage,cashback,coupon',
            'discount_value' => 'required|numeric',
            'scope_type' => 'required|in:platform,merchant_tier,category,lender,tenure,geo',
            'scope_value' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'recurrence' => 'nullable|string',
            'blackout_dates' => 'nullable|array',
            'budget_cap' => 'nullable|numeric',
            'auto_pause' => 'boolean',
            'is_platform_offer' => 'boolean',
            'merchant_id' => 'nullable|exists:merchants,id'
        ]);

        $validated['status'] = 'Pending';
        if ($validated['is_platform_offer'] ?? false) {
            $validated['status'] = 'Active'; // Platform offers might skip the merchant approval queue
        }

        $offer = Offer::create($validated);

        return response()->json($offer, 201);
    }

    #[OA\Get(
        path: "/api/v1/admin/offers/{id}",
        summary: "Show Offer Details",
        security: [["sanctum" => []]],
        tags: ["Offer"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function show($id)
    {
        return response()->json(Offer::findOrFail($id));
    }

    #[OA\Put(
        path: "/api/v1/admin/offers/{id}",
        summary: "Update Offer",
        security: [["sanctum" => []]],
        tags: ["Offer"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "title", type: "string", example: "Diwali Special Cashback (Extended)")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'offer_type' => 'sometimes|in:flat,percentage,cashback,coupon',
            'discount_value' => 'sometimes|numeric',
            'scope_type' => 'sometimes|in:platform,merchant_tier,category,lender,tenure,geo',
            'scope_value' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'recurrence' => 'nullable|string',
            'blackout_dates' => 'nullable|array',
            'budget_cap' => 'nullable|numeric',
            'auto_pause' => 'boolean',
        ]);

        $offer->update($validated);

        return response()->json($offer);
    }

    #[OA\Delete(
        path: "/api/v1/admin/offers/{id}",
        summary: "Delete Offer",
        security: [["sanctum" => []]],
        tags: ["Offer"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted")
        ]
    )]
    public function destroy($id)
    {
        Offer::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    // Offer Approval Queue logic
    #[OA\Post(
        path: "/api/v1/admin/offers/{id}/approve",
        summary: "Approve Offer",
        security: [["sanctum" => []]],
        tags: ["Offer"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function approve(Request $request, $id)
    {
        $offer = Offer::findOrFail($id);
        $offer->update([
            'status' => 'Approved', // or 'Active' depending on start_date
            'approval_reason' => 'Approved by admin',
        ]);

        return response()->json(['message' => 'Offer approved', 'offer' => $offer]);
    }

    #[OA\Post(
        path: "/api/v1/admin/offers/{id}/reject",
        summary: "Reject Offer",
        security: [["sanctum" => []]],
        tags: ["Offer"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "Discount value too high.")
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
        
        $offer = Offer::findOrFail($id);
        $offer->update([
            'status' => 'Rejected',
            'approval_reason' => $request->reason,
        ]);

        return response()->json(['message' => 'Offer rejected', 'offer' => $offer]);
    }
}
