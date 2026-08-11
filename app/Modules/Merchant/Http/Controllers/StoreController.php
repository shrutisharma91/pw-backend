<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $stores = Store::where('merchant_id', $this->scopedMerchantId())
            ->withCount('products')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $stores,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'address'    => 'required|string',
            'status'     => 'string|in:active,inactive',
        ]);

        $validated['merchant_id'] = $this->scopedMerchantId();
        $validated['status'] = $validated['status'] ?? 'active';

        $store = Store::create($validated);

        return response()->json(['success' => true, 'data' => $store], 201);
    }

    public function show($id)
    {
        $store = Store::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        return response()->json(['success' => true, 'data' => $store]);
    }

    public function update(Request $request, $id)
    {
        $store = Store::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        
        $validated = $request->validate([
            'name'       => 'string|max:255',
            'address'    => 'string',
            'status'     => 'string|in:active,inactive',
        ]);

        $store->update($validated);

        return response()->json(['success' => true, 'data' => $store]);
    }

    public function destroy($id)
    {
        $store = Store::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        $store->delete();

        return response()->json(['success' => true, 'message' => 'Store deleted.']);
    }
}
