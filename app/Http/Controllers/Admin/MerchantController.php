<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Merchant;

class MerchantController extends Controller
{
    public function index(Request $request)
    {
        // Screen 14 - Merchant Directory
        $merchants = Merchant::query();
        
        // Example filters
        if ($request->has('status')) {
            $merchants->where('status', $request->status);
        }
        
        return response()->json($merchants->paginate(15));
    }

    public function show($id)
    {
        // Screen 16 - Merchant 360 Profile
        $merchant = Merchant::findOrFail($id);
        return response()->json($merchant);
    }

    public function approve(Request $request, $id)
    {
        // Screen 15 - Approve Merchant
        $request->validate(['comment' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Approved';
        $merchant->save();
        
        return response()->json(['message' => 'Merchant approved', 'merchant' => $merchant]);
    }

    public function reject(Request $request, $id)
    {
        // Screen 15 - Reject Merchant
        $request->validate(['reason' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Rejected';
        $merchant->save();
        
        return response()->json(['message' => 'Merchant rejected', 'merchant' => $merchant]);
    }

    public function reKyc(Request $request, $id)
    {
        // Screen 19 - Trigger Re-KYC
        $request->validate(['reason' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Re-KYC';
        $merchant->save();
        
        return response()->json(['message' => 'Re-KYC triggered', 'merchant' => $merchant]);
    }

    public function suspend(Request $request, $id)
    {
        // Screen 19 - Suspend Merchant
        $request->validate(['reason' => 'required|string']);
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Suspended';
        $merchant->suspension_reason = $request->reason;
        $merchant->save();
        
        return response()->json(['message' => 'Merchant suspended', 'merchant' => $merchant]);
    }
}
