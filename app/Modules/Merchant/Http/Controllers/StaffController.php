<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends MerchantBaseController
{
    public function index(Request $request)
    {
        $staff = User::where('merchant_id', $this->scopedMerchantId())
            ->whereIn('role', ['merchant_admin', 'store_manager'])
            ->with(['store'])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $staff,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'role'     => 'required|in:merchant_admin,store_manager',
            'store_id' => 'required_if:role,store_manager|exists:stores,id',
            'password' => 'required|string|min:8',
        ]);

        $validated['merchant_id'] = $this->scopedMerchantId();
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;

        $user = User::create($validated);

        return response()->json(['success' => true, 'data' => $user], 201);
    }

    public function show($id)
    {
        $user = User::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        return response()->json(['success' => true, 'data' => $user]);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        
        $validated = $request->validate([
            'name'     => 'string|max:255',
            'role'     => 'in:merchant_admin,store_manager',
            'store_id' => 'required_if:role,store_manager|exists:stores,id',
            'is_active'=> 'boolean',
        ]);

        $user->update($validated);

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function destroy($id)
    {
        $user = User::where('merchant_id', $this->scopedMerchantId())->findOrFail($id);
        $user->delete();

        return response()->json(['success' => true, 'message' => 'Staff deleted.']);
    }
}
