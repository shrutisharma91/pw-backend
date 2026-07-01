<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    #[OA\Get(
        path: "/api/v1/admin/products",
        summary: "Get Product Directory",
        security: [["sanctum" => []]],
        tags: ["Product"],
        parameters: [
            new OA\Parameter(name: "category_id", in: "query", required: false, schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "brand_id", in: "query", required: false, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function index(Request $request)
    {
        $products = Product::with(['merchant', 'category', 'brand']);
        
        if ($request->has('category_id')) $products->where('category_id', $request->category_id);
        if ($request->has('brand_id')) $products->where('brand_id', $request->brand_id);
        
        // Use a massive number to effectively return "no limit" while preserving the expected frontend JSON structure
        $perPage = $request->input('per_page', 1000000);
        return response()->json($products->paginate($perPage));
    }

    #[OA\Post(
        path: "/api/v1/admin/products/{id}/flag",
        summary: "Flag Product for Review",
        security: [["sanctum" => []]],
        tags: ["Product"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function flag($id)
    {
        $product = Product::findOrFail($id);
        $product->flagged_for_review = true;
        $product->save();

        return response()->json(['message' => 'Product flagged for review', 'product' => $product]);
    }

    #[OA\Post(
        path: "/api/v1/admin/products/{id}/delist",
        summary: "Delist Product",
        security: [["sanctum" => []]],
        tags: ["Product"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "reason", type: "string", example: "IP Infringement")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function delist(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $product = Product::findOrFail($id);
        $product->status = 'delisted';
        $product->delist_reason = $request->reason;
        $product->save();

        return response()->json(['message' => 'Product force-delisted successfully', 'product' => $product]);
    }

    #[OA\Post(
        path: "/api/v1/admin/products/bulk-financing-toggle",
        summary: "Bulk Toggle Financing Eligibility",
        security: [["sanctum" => []]],
        tags: ["Product"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "category_id", type: "integer", example: 1),
                    new OA\Property(property: "financing_eligibility", type: "boolean", example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function bulkFinancingToggle(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'financing_eligibility' => 'required|boolean',
        ]);

        Product::where('category_id', $request->category_id)
            ->update(['financing_eligibility' => $request->financing_eligibility]);

        return response()->json(['message' => 'Bulk financing eligibility toggled successfully']);
    }

    #[OA\Post(
        path: "/api/v1/admin/products/detect-duplicates",
        summary: "Detect Duplicate SKUs",
        security: [["sanctum" => []]],
        tags: ["Product"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function detectDuplicates(Request $request)
    {
        // Mock implementation for duplicate detection
        $duplicates = [
            ['sku' => 'SKU-1001', 'merchant_ids' => [1, 5], 'product_name' => 'iPhone 15 Pro'],
            ['sku' => 'SKU-2005', 'merchant_ids' => [2, 8, 12], 'product_name' => 'Samsung S24 Ultra']
        ];
        return response()->json(['duplicates' => $duplicates]);
    }

    #[OA\Post(
        path: "/api/v1/admin/products/bulk-import",
        summary: "Bulk Import Products",
        security: [["sanctum" => []]],
        tags: ["Product"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: "file", type: "string", format: "binary", description: "CSV file for bulk import")
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);
        
        if (!$header) {
            return response()->json(['message' => 'Invalid CSV format'], 400);
        }
        
        // Remove BOM from the first column if present
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        
        // Clean up headers (trim and lowercase)
        $header = array_map(function($col) { return strtolower(trim($col)); }, $header);
        
        $products = [];
        $now = now();
        
        $merchantId = $request->input('merchant_id');

        while (($row = fgetcsv($handle)) !== false) {
            if (count($header) !== count($row)) {
                continue;
            }
            
            $data = array_combine($header, $row);
            
            // Allow merchant_id from form payload if missing in CSV
            $rowMerchantId = $data['merchant_id'] ?? $merchantId;
            
            if (!$rowMerchantId) {
                return response()->json(['message' => 'merchant_id is required either in CSV or form payload'], 422);
            }

            $products[] = [
                'merchant_id' => $rowMerchantId,
                'category_id' => !empty($data['category_id']) ? $data['category_id'] : null,
                'brand_id' => !empty($data['brand_id']) ? $data['brand_id'] : null,
                'name' => $data['name'] ?? 'Unknown Product',
                'sku' => $data['sku'] ?? null,
                'price' => $data['price'] ?? 0,
                'status' => $data['status'] ?? 'active',
                'financing_eligibility' => isset($data['financing_eligibility']) ? filter_var($data['financing_eligibility'], FILTER_VALIDATE_BOOLEAN) : true,
                'flagged_for_review' => false,
                'delist_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        fclose($handle);

        if (!empty($products)) {
            foreach (array_chunk($products, 500) as $chunk) {
                Product::insert($chunk);
            }
        }
        
        return response()->json(['message' => count($products) . ' products imported successfully']);
    }
}
