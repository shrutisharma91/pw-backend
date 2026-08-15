<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;

class OfferController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $offers = Offer::where('merchant_id', $this->scopedMerchantId());

        if ($request->status) {
            $offers->where('status', $request->status);
        }

        return response()->json([
            'success' => true,
            'data'    => $offers->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:cashback,discount,subvention_boost,zero_emi',
            'value' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'target_categories' => 'nullable|array',
            'target_stores' => 'nullable|array',
            'budget_limit' => 'nullable|numeric',
        ]);

        $validated['merchant_id'] = $this->scopedMerchantId();
        $validated['status'] = 'draft'; 
        $validated['budget_consumed'] = 0;

        $offer = Offer::create($validated);

        return response()->json(['success' => true, 'data' => $offer], 201);
    }

    public function show($id)
    {
        $offer = Offer::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        return response()->json(['success' => true, 'data' => $offer]);
    }

    public function update(Request $request, $id)
    {
        $offer = Offer::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'string|max:255',
            'type' => 'string|in:cashback,discount,subvention_boost,zero_emi',
            'value' => 'numeric',
            'end_date' => 'date|after:start_date',
            'target_categories' => 'nullable|array',
            'target_stores' => 'nullable|array',
            'budget_limit' => 'nullable|numeric',
            'status' => 'string|in:draft,active,inactive,expired',
        ]);

        $offer->update($validated);

        return response()->json(['success' => true, 'data' => $offer]);
    }

    public function destroy($id)
    {
        $offer = Offer::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        
        if ($offer->status === 'active') {
            return response()->json(['success' => false, 'message' => 'Cannot delete an active offer.'], 403);
        }

        $offer->delete();

        return response()->json(['success' => true, 'message' => 'Offer deleted.']);
    }
}
