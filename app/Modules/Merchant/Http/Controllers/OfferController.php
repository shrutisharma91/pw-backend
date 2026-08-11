<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;

class OfferController extends MerchantBaseController
{
    public function index(Request $request)
    {
        // Ideally offers are linked to a merchant_id.
        // If there's no merchant_id on offers table, we assume global offers for now,
        // or we filter by something else. 
        // We will assume the offers table has merchant_id for phase 4.
        $offers = clone Offer::query();
        
        // Scope offers to merchant if the column exists
        if (\Schema::hasColumn('offers', 'merchant_id')) {
            $offers = $offers->where('merchant_id', $this->scopedMerchantId());
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
            'type' => 'required|string',
            'value' => 'required|numeric',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        if (\Schema::hasColumn('offers', 'merchant_id')) {
            $validated['merchant_id'] = $this->scopedMerchantId();
        }
        $validated['status'] = 'Pending'; // Needs super admin approval

        $offer = Offer::create($validated);

        return response()->json(['success' => true, 'data' => $offer], 201);
    }

    public function show($id)
    {
        $offer = clone Offer::query();
        if (\Schema::hasColumn('offers', 'merchant_id')) {
            $offer = $offer->where('merchant_id', $this->scopedMerchantId());
        }
        return response()->json(['success' => true, 'data' => $offer->findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $offerQuery = clone Offer::query();
        if (\Schema::hasColumn('offers', 'merchant_id')) {
            $offerQuery = $offerQuery->where('merchant_id', $this->scopedMerchantId());
        }
        $offer = $offerQuery->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'string|max:255',
            'type' => 'string',
            'value' => 'numeric',
            'end_date' => 'date',
        ]);

        $offer->update($validated);

        return response()->json(['success' => true, 'data' => $offer]);
    }

    public function destroy($id)
    {
        $offerQuery = clone Offer::query();
        if (\Schema::hasColumn('offers', 'merchant_id')) {
            $offerQuery = $offerQuery->where('merchant_id', $this->scopedMerchantId());
        }
        $offer = $offerQuery->findOrFail($id);
        $offer->delete();

        return response()->json(['success' => true, 'message' => 'Offer deleted.']);
    }
}
