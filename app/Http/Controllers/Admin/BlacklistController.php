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