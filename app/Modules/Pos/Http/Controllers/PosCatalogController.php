<?php

namespace App\Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class PosCatalogController extends PosBaseController
{
    /**
     * Fetch products available for financing at this store/merchant.
     */
    public function index(Request $request)
    {
        $merchantId = $this->getMerchantId($request);
        
        // For phase 5, we fetch products linked to the merchant.
        $query = Product::where('merchant_id', $merchantId)
            ->where('status', 'active')
            ->where('financing_eligibility', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $products->items(),
            'meta'    => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total()
            ]
        ]);
    }
}
