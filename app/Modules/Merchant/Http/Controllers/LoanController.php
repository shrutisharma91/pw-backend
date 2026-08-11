<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\LoanApplication;
use Illuminate\Http\Request;

class LoanController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $loans = LoanApplication::where('merchant_id', $this->scopedMerchantId());

        if ($this->scopedStoreId()) {
            $loans->where('store_id', $this->scopedStoreId());
        }

        $loans = $loans->with(['customer', 'product'])
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $loans->items(),
            'meta'    => [
                'current_page' => $loans->currentPage(),
                'last_page'    => $loans->lastPage(),
                'total'        => $loans->total(),
            ]
        ]);
    }
}
