<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\SettlementEntry;
use Illuminate\Http\Request;

class SettlementController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $query = SettlementEntry::where('merchant_id', $this->scopedMerchantId())
            ->with(['loanApplication.customer', 'loanApplication.store']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }
        
        if ($request->boolean('export')) {
            // Mock export
            return response()->json([
                'success' => true,
                'data' => $query->get()
            ]);
        }

        $settlements = $query->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $settlements->items(),
            'meta'    => [
                'current_page' => $settlements->currentPage(),
                'last_page'    => $settlements->lastPage(),
                'total'        => $settlements->total(),
            ]
        ]);
    }

    public function dispute(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
            'amount_claimed' => 'required|numeric'
        ]);

        $settlement = SettlementEntry::where('merchant_id', $this->scopedMerchantId())
            ->findOrFail($id);
            
        // In reality, we'd log this in a disputes table
        // $settlement->status = 'disputed'; 
        // $settlement->save();

        return response()->json([
            'success' => true,
            'message' => 'Dispute raised successfully.',
            'data' => array_merge($validated, ['settlement_id' => $id])
        ]);
    }
}
