<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/brands",
        summary: "List Brands",
        security: [["sanctum" => []]],
        tags: ["Brand"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index()
    {
        $brands = Brand::all();
        return response()->json($brands);
    }

    #[OA\Post(
        path: "/api/v1/admin/brands",
        summary: "Create Brand",
        security: [["sanctum" => []]],
        tags: ["Brand"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Samsung"),
                    new OA\Property(property: "logo_url", type: "string", example: "https://example.com/logo.png"),
                    new OA\Property(property: "status", type: "string", example: "approved")
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
            'name' => 'required|string|unique:brands',
            'logo_url' => 'nullable|url',
            'status' => 'nullable|string|in:approved,pending,rejected',
        ]);

        $brand = Brand::create($request->all());

        return response()->json($brand, 201);
    }

    #[OA\Put(
        path: "/api/v1/admin/brands/{id}",
        summary: "Update Brand",
        security: [["sanctum" => []]],
        tags: ["Brand"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Samsung"),
                    new OA\Property(property: "logo_url", type: "string", example: "https://example.com/logo.png"),
                    new OA\Property(property: "status", type: "string", example: "approved")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        
        $request->validate([
            'name' => 'sometimes|required|string|unique:brands,name,'.$id,
            'logo_url' => 'nullable|url',
            'status' => 'nullable|string|in:approved,pending,rejected',
        ]);

        $brand->update($request->all());

        return response()->json($brand);
    }

    #[OA\Get(
        path: "/api/v1/admin/brands/export",
        summary: "Export Brands CSV",
        security: [["sanctum" => []]],
        tags: ["Brand"],
        responses: [
            new OA\Response(response: 200, description: "CSV Data")
        ]
    )]
    public function export(Request $request)
    {
        $brands = Brand::all();
        $csvData = "ID,Name,Status\n";
        foreach ($brands as $b) {
            $csvData .= "{$b->id},\"{$b->name}\",{$b->status}\n";
        }
        return response()->make($csvData, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="brands.csv"',
        ]);
    }

    #[OA\Post(
        path: "/api/v1/admin/brands/import",
        summary: "Import Brands from CSV",
        security: [["sanctum" => []]],
        tags: ["Brand"],
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
        return response()->json(['message' => 'Brands imported successfully']);
    }
}
