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
        
        // Advanced Filters
        if ($request->has('status')) $merchants->where('status', $request->status);
        if ($request->has('region')) $merchants->where('region', $request->region);
        if ($request->has('category')) $merchants->where('category', $request->category);
        if ($request->has('sales_exec_id')) $merchants->where('sales_exec_id', $request->sales_exec_id);
        if ($request->has('signup_date')) $merchants->whereDate('created_at', clone new \Carbon\Carbon($request->signup_date));
        
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

    public function bulkApprove(Request $request)
    {
        $request->validate([
            'merchant_ids' => 'required|array',
            'comment' => 'required|string'
        ]);
        Merchant::whereIn('id', $request->merchant_ids)->update(['status' => 'Approved']);
        return response()->json(['message' => 'Bulk approval successful']);
    }

    public function bulkReject(Request $request)
    {
        $request->validate([
            'merchant_ids' => 'required|array',
            'reason' => 'required|string'
        ]);
        Merchant::whereIn('id', $request->merchant_ids)->update(['status' => 'Rejected']);
        return response()->json(['message' => 'Bulk rejection successful']);
    }

    public function export(Request $request)
    {
        // Mock export logic returning a CSV download
        return response()->json(['download_url' => 'http://localhost/exports/merchants_'.time().'.csv']);
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

    public function bulkReKyc(Request $request)
    {
        $request->validate([
            'merchant_ids' => 'required|array',
            'reason' => 'required|string'
        ]);
        Merchant::whereIn('id', $request->merchant_ids)->update(['status' => 'Re-KYC']);
        return response()->json(['message' => 'Bulk Re-KYC triggered']);
    }

    public function suspend(Request $request, $id)
    {
        // Screen 19 - Suspend Merchant
        $request->validate(['reason_code' => 'required|string']); // using the new taxonomy code
        $merchant = Merchant::findOrFail($id);
        $merchant->status = 'Suspended';
        $merchant->suspension_reason = $request->reason_code;
        $merchant->save();
        
        return response()->json(['message' => 'Merchant suspended', 'merchant' => $merchant]);
    }

    public function sendNotice(Request $request, $id)
    {
        $request->validate(['notice_text' => 'required|string']);
        // logic to send email/push notice
        return response()->json(['message' => 'Notice sent successfully']);
    }

    public function escalateToRisk(Request $request, $id)
    {
        $request->validate(['escalation_reason' => 'required|string']);
        // logic to notify risk team
        return response()->json(['message' => 'Escalated to Risk team']);
    }
}
