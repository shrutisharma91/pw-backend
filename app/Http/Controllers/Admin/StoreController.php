<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $stores = Store::with('merchant')->get();
        // In a real scenario we'd add filters for cluster, region, status, etc.
        return response()->json($stores);
    }

    public function show($id)
    {
        $store = Store::with(['merchant', 'products' => function($q) {
            $q->withPivot('stock_quantity');
        }])->findOrFail($id);

        // Mock loan applications for this store since Loan model doesn't exist yet
        $store->mock_recent_loans = [
            'last_30_days' => rand(5, 20),
            'last_90_days' => rand(15, 60),
        ];

        return response()->json($store);
    }

    public function deactivate(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $store = Store::findOrFail($id);
        $store->status = 'deactivated';
        $store->deactivation_reason = $request->reason;
        $store->save();

        return response()->json(['message' => 'Store deactivated successfully', 'store' => $store]);
    }

    public function export(Request $request)
    {
        // Mock export logic returning a map/store PNG/CSV download
        return response()->json(['download_url' => 'http://localhost/exports/stores_map_'.time().'.png']);
    }
}
