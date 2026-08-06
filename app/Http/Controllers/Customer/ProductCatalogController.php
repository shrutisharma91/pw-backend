<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Phase 2 Screen 14 — EMI Product Catalog
 */
class ProductCatalogController extends Controller
{
    #[OA\Get(
        path: '/api/v1/customer/products',
        summary: 'List EMI-eligible products for customer catalog',
        tags: ['Phase2-Customer'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product list'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Product::with(['brand', 'category'])
            ->where('financing_eligibility', true)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'active')->orWhere('status', 'Active');
            });

        if ($request->filled('category') && strcasecmp($request->category, 'All') !== 0) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->category . '%');
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$search}%"));
            });
        }

        $products = $query->orderBy('name')->get()->map(function (Product $p) {
            $price = (float) $p->price;
            $emi6 = $price > 0 ? (int) round($price / 6) : 0;

            return [
                'id'         => $p->id,
                'sku'        => $p->sku,
                'name'       => $p->name,
                'category'   => $p->category?->name,
                'brand'      => $p->brand?->name,
                'price'      => $price,
                'emi_badge'  => '0% No-Cost EMI',
                'emi_starts' => '₹' . number_format($emi6, 0, '.', ',') . '/mo',
                'specs'      => $p->sku,
            ];
        });

        $categories = Product::with('category')
            ->where('financing_eligibility', true)
            ->get()
            ->pluck('category.name')
            ->filter()
            ->unique()
            ->values()
            ->prepend('All');

        return response()->json([
            'success'    => true,
            'categories' => $categories,
            'data'       => $products,
        ]);
    }
}
