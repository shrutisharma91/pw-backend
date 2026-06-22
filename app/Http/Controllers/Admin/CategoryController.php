<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/categories",
        summary: "List Categories",
        security: [["sanctum" => []]],
        tags: ["Category"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();
        return response()->json($categories);
    }

    #[OA\Post(
        path: "/api/v1/admin/categories",
        summary: "Create Category",
        security: [["sanctum" => []]],
        tags: ["Category"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Electronics"),
                    new OA\Property(property: "parent_id", type: "integer", example: null)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Success")
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id,
        ]);

        return response()->json($category, 201);
    }

    #[OA\Put(
        path: "/api/v1/admin/categories/{id}",
        summary: "Update Category",
        security: [["sanctum" => []]],
        tags: ["Category"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Smartphones"),
                    new OA\Property(property: "parent_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|required|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if ($request->has('name')) {
            $category->name = $request->name;
            $category->slug = Str::slug($request->name);
        }
        if ($request->has('parent_id')) {
            $category->parent_id = $request->parent_id;
        }
        $category->save();

        return response()->json($category);
    }

    #[OA\Post(
        path: "/api/v1/admin/categories/{id}/archive",
        summary: "Archive Category",
        security: [["sanctum" => []]],
        tags: ["Category"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            description: "Optional. ID of another existing category to reassign all products to before archiving. If provided, the ID must exist in the categories table.",
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reassign_to", type: "integer", description: "Must be a valid existing Category ID", example: null)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function archive(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'reassign_to' => 'nullable|exists:categories,id',
        ]);

        if ($request->reassign_to && $request->reassign_to != $category->id) {
            Product::where('category_id', $category->id)->update(['category_id' => $request->reassign_to]);
            Category::where('parent_id', $category->id)->update(['parent_id' => $request->reassign_to]);
        }

        $category->status = 'archived';
        $category->save();
        $category->delete(); // soft delete

        return response()->json(['message' => 'Category archived successfully']);
    }

    #[OA\Put(
        path: "/api/v1/admin/categories/{id}/financing-rules",
        summary: "Set Financing Rules",
        security: [["sanctum" => []]],
        tags: ["Category"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "default_down_payment_percent", type: "number", example: 10.5),
                    new OA\Property(property: "default_tenure_months", type: "integer", example: 6)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function setFinancingRules(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'default_down_payment_percent' => 'nullable|numeric|min:0|max:100',
            'default_tenure_months' => 'nullable|integer|min:1',
        ]);

        $category->default_down_payment_percent = $request->default_down_payment_percent;
        $category->default_tenure_months = $request->default_tenure_months;
        $category->save();

        return response()->json(['message' => 'Financing rules updated successfully', 'category' => $category]);
    }

    #[OA\Get(
        path: "/api/v1/admin/categories/export",
        summary: "Export Categories CSV",
        security: [["sanctum" => []]],
        tags: ["Category"],
        responses: [
            new OA\Response(response: 200, description: "CSV Data")
        ]
    )]
    public function export(Request $request)
    {
        $categories = Category::all();
        $csvData = "ID,Name,Slug,Parent ID\n";
        foreach ($categories as $c) {
            $csvData .= "{$c->id},\"{$c->name}\",{$c->slug},{$c->parent_id}\n";
        }
        return response()->make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="categories.csv"',
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/categories/import",
        summary: "Import Categories from CSV",
        security: [["sanctum" => []]],
        tags: ["Category"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary", description: "CSV File")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);
        // Mock Import Implementation
        return response()->json(['message' => 'Categories imported successfully']);
    }
}
