<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['merchant', 'category', 'brand'])->get();
        // Here we could add duplicate SKU detection flags if needed
        return response()->json($products);
    }

    public function flag($id)
    {
        $product = Product::findOrFail($id);
        $product->flagged_for_review = true;
        $product->save();

        return response()->json(['message' => 'Product flagged for review', 'product' => $product]);
    }

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
}
