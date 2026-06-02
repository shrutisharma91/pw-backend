<?php

namespace App\Http\Controllers\Admin;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\MerchantCategory;
use Illuminate\Http\Request;

class MerchantCategoryController extends Controller
{
    #[OA\Post(
        path: "/api/v1/admin/merchant-categories/{id}/map",
        summary: "Map Category to Master",
        security: [["sanctum" => []]],
        tags: ["MerchantCategory"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "mapped_category_id", type: "integer", example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function mapToMaster(Request $request, $id)
    {
        $request->validate([
            'mapped_category_id' => 'required|exists:categories,id',
        ]);

        $merchantCategory = MerchantCategory::findOrFail($id);
        $merchantCategory->mapped_category_id = $request->mapped_category_id;
        $merchantCategory->save();

        return response()->json(['message' => 'Category mapped successfully', 'merchant_category' => $merchantCategory]);
    }
}
