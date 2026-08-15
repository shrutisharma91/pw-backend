<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $query = Store::where('merchant_id', $this->scopedMerchantId())
            ->withCount('products')
            ->withCount(['posTerminals as active_pos_count' => function ($q) {
                $q->where('status', 'active');
            }]);

        if ($request->region) {
            $query->where('region', $request->region);
        }
        if ($request->city) {
            $query->where('city', $request->city);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $stores = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $stores,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'store_code' => 'nullable|string|max:100',
            'address'    => 'required|string',
            'city'       => 'nullable|string|max:100',
            'region'     => 'nullable|string|max:100',
            'pin_code'   => 'nullable|string|max:20',
            'manager_id' => 'nullable|exists:users,id',
            'working_hours' => 'nullable|array',
            'status'     => 'string|in:active,inactive',
        ]);

        $validated['merchant_id'] = $this->scopedMerchantId();
        $validated['status'] = $validated['status'] ?? 'active';

        $store = Store::create($validated);

        return response()->json(['success' => true, 'data' => $store], 201);
    }

    public function show($id)
    {
        $store = Store::where('merchant_id', $this->scopedMerchantId())
            ->with('posTerminals')
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $store]);
    }

    public function update(Request $request, $id)
    {
        $store = Store::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        
        $validated = $request->validate([
            'name'       => 'string|max:255',
            'store_code' => 'nullable|string|max:100',
            'address'    => 'string',
            'city'       => 'nullable|string|max:100',
            'region'     => 'nullable|string|max:100',
            'pin_code'   => 'nullable|string|max:20',
            'manager_id' => 'nullable|exists:users,id',
            'working_hours' => 'nullable|array',
            'status'     => 'string|in:active,inactive',
            'deactivation_reason' => 'nullable|string',
        ]);

        $store->update($validated);

        return response()->json(['success' => true, 'data' => $store]);
    }

    public function destroy($id)
    {
        $store = Store::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        
        // Instead of hard delete, maybe deactivate? For now keep delete as requested, but we have deactivate with reason
        if (request()->has('deactivation_reason')) {
            $store->update([
                'status' => 'inactive',
                'deactivation_reason' => request()->deactivation_reason
            ]);
            return response()->json(['success' => true, 'message' => 'Store deactivated.']);
        }

        $store->delete();

        return response()->json(['success' => true, 'message' => 'Store deleted.']);
    }
}
