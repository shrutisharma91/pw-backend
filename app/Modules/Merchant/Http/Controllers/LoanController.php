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
        } elseif ($request->store_id) {
            $loans->where('store_id', $request->store_id);
        }

        if ($request->status) {
            $loans->where('status', $request->status);
        }

        if ($request->start_date && $request->end_date) {
            $loans->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        if ($request->search) {
            $loans->whereHas('customer', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('mobile', 'like', "%{$request->search}%");
            });
        }

        $loans = $loans->with(['customer', 'product', 'store'])->latest();

        if ($request->boolean('export')) {
            // In a real application, we would generate a CSV and return a download response
            // For Phase 4, just return the raw data without pagination for the export action
            return response()->json([
                'success' => true,
                'data' => $loans->get()
            ]);
        }

        $paginated = $loans->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ]
        ]);
    }
}
