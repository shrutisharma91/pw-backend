<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $products = Product::where('merchant_id', $this->scopedMerchantId())
            ->with(['category', 'brand'])
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $products->items(),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku'                   => 'required|string|max:255',
            'name'                  => 'required|string|max:255',
            'price'                 => 'required|numeric|min:0',
            'category_id'           => 'required|exists:categories,id',
            'brand_id'              => 'required|exists:brands,id',
            'financing_eligibility' => 'boolean',
        ]);

        $validated['merchant_id'] = $this->scopedMerchantId();
        $validated['status'] = 'active';

        $product = Product::create($validated);

        return response()->json(['success' => true, 'data' => $product], 201);
    }

    public function show($id)
    {
        $product = Product::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        return response()->json(['success' => true, 'data' => $product]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        
        $validated = $request->validate([
            'name'                  => 'string|max:255',
            'price'                 => 'numeric|min:0',
            'category_id'           => 'exists:categories,id',
            'brand_id'              => 'exists:brands,id',
            'financing_eligibility' => 'boolean',
            'status'                => 'string|in:active,inactive',
        ]);

        $product->update($validated);

        return response()->json(['success' => true, 'data' => $product]);
    }

    public function destroy($id)
    {
        $product = Product::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        $product->delete();

        return response()->json(['success' => true, 'message' => 'Product deleted.']);
    }

    public function bulkUpload(Request $request)
    {
        // Simple mock for CSV upload processing
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'CSV queued for processing.'
        ]);
    }
}
