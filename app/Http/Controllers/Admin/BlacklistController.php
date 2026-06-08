<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BlacklistEntry;

class BlacklistController extends Controller
{
    // Screen 39: Blacklist Manager
    #[OA\Get(
        path: "/api/v1/admin/blacklist",
        summary: "index Blacklist",
        security: [["sanctum" => []]],
        tags: ["Blacklist"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $query = BlacklistEntry::with('overrideApprover');

        if ($request->has('category')) $query->where('category', $request->category);
        if ($request->has('search')) $query->where('value', 'like', '%' . $request->search . '%');

        return response()->json($query->paginate(20));
    }

    #[OA\Post(
        path: "/api/v1/admin/blacklist",
        summary: "store Blacklist",
        security: [["sanctum" => []]],
        tags: ["Blacklist"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["category", "value", "reason", "severity"],
                properties: [
                    new OA\Property(property: "category", type: "string", example: "PAN"),
                    new OA\Property(property: "value", type: "string", example: "ABCDE1234F"),
                    new OA\Property(property: "reason", type: "string", example: "Fraudulent application history"),
                    new OA\Property(property: "severity", type: "string", example: "High")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|string',
            'value' => 'required|string',
            'reason' => 'required|string',
            'severity' => 'required|string'
        ]);

        $entry = BlacklistEntry::create($request->all());

        return response()->json(['message' => 'Blacklist entry added', 'entry' => $entry]);
    }

    #[OA\Post(
        path: "/api/v1/admin/blacklist/bulk-import",
        summary: "bulkImport Blacklist",
        security: [["sanctum" => []]],
        tags: ["Blacklist"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function bulkImport(Request $request)
    {
        // Mock successful bulk import
        return response()->json(['message' => 'CSV imported successfully. 150 entries added.']);
    }

    #[OA\Post(
        path: "/api/v1/admin/blacklist/{id}/remove",
        summary: "remove Blacklist",
        security: [["sanctum" => []]],
        tags: ["Blacklist"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["reason"],
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "False positive, verified by support.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function remove(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);

        $entry = BlacklistEntry::findOrFail($id);
        $entry->status = 'Removed';
        // audit-stamped logic here
        $entry->save();

        return response()->json(['message' => 'Entry removed successfully', 'entry' => $entry]);
    }

    #[OA\Post(
        path: "/api/v1/admin/blacklist/{id}/whitelist-override",
        summary: "whitelistOverride Blacklist",
        security: [["sanctum" => []]],
        tags: ["Blacklist"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["override_approved_by", "reason"],
                properties: [
                    new OA\Property(property: "override_approved_by", type: "integer", example: 3),
                    new OA\Property(property: "reason", type: "string", example: "Management exception for enterprise client.")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]

    public function whitelistOverride(Request $request, $id)
    {
        $request->validate([
            'override_approved_by' => 'required|integer', // dual approval
            'reason' => 'required|string'
        ]);

        $entry = BlacklistEntry::findOrFail($id);
        $entry->status = 'Whitelisted';
        $entry->override_approved_by = $request->override_approved_by;
        $entry->save();

        return response()->json(['message' => 'Whitelist override applied', 'entry' => $entry]);
    }
}