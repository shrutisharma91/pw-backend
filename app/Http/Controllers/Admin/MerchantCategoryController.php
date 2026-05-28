<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantCategory;
use Illuminate\Http\Request;

class MerchantCategoryController extends Controller
{
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
