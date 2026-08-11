<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\SettlementEntry;
use Illuminate\Http\Request;

class SettlementController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $settlements = SettlementEntry::where('merchant_id', $this->scopedMerchantId())
            ->with(['loanApplication'])
            ->latest()
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
}
