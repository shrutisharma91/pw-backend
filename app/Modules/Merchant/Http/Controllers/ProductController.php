<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $query = Product::where('merchant_id', $this->scopedMerchantId())
            ->with(['category', 'brand']);

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->min_price) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->max_price) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->has('emi_eligible')) {
            $query->where('financing_eligibility', filter_var($request->emi_eligible, FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->paginate($request->get('per_page', 15));

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
        $request->validate([
            'file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('file');
        $csvData = file_get_contents($file);
        $lines = explode(PHP_EOL, $csvData);
        $header = str_getcsv(array_shift($lines));
        
        $imported = 0;
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $row = str_getcsv($line);
            if (count($header) === count($row)) {
                $data = array_combine($header, $row);
                if (isset($data['sku']) && isset($data['name']) && isset($data['price'])) {
                    Product::updateOrCreate(
                        [
                            'merchant_id' => $this->scopedMerchantId(),
                            'sku' => $data['sku']
                        ],
                        [
                            'name' => $data['name'],
                            'price' => $data['price'],
                            'financing_eligibility' => filter_var($data['emi_eligible'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'status' => 'active',
                            'category_id' => 1, // Fallback dummy category for parsing
                            'brand_id' => 1,    // Fallback dummy brand for parsing
                        ]
                    );
                    $imported++;
                }
            }
        }

        return response()->json([
            'success' => true, 
            'message' => "Successfully imported $imported products.",
            'imported_count' => $imported
        ]);
    }
}
