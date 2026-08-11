<?php

namespace App\Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosStaffController extends PosBaseController
{
    /**
     * Get list of staff for this store (Screen 41)
     */
    public function index(Request $request)
    {
        $storeId = $this->getStoreId($request);
        $merchantId = $this->getMerchantId($request);
        
        // We find users who belong to this merchant and have this store_id in their store_ids JSON array
        // Since Laravel JSON queries can be tricky, we'll do a simple whereJsonContains
        $staff = User::where('merchant_id', $merchantId)
            ->whereJsonContains('store_ids', (string) $storeId)
            ->orWhereJsonContains('store_ids', (int) $storeId)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $staff->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'mobile' => $user->mobile,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'last_login' => $user->last_login_at,
                ];
            })
        ]);
    }

    /**
     * Add new staff to this store (Screen 41)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'mobile' => 'required|string|size:10',
            'role' => 'required|in:store_manager,cashier',
        ]);

        $storeId = $this->getStoreId($request);
        $merchantId = $this->getMerchantId($request);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'],
            'password' => Hash::make(Str::random(12)), // Auto generate password
            'role' => $validated['role'],
            'merchant_id' => $merchantId,
            'store_ids' => [$storeId], // Assign to current store
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member added successfully.',
            'data' => $user
        ]);
    }

    /**
     * Toggle staff status (Screen 41)
     */
    public function toggleActive(Request $request, $id)
    {
        $storeId = $this->getStoreId($request);
        $merchantId = $this->getMerchantId($request);

        $user = User::where('merchant_id', $merchantId)
            ->where(function($q) use ($storeId) {
                $q->whereJsonContains('store_ids', (string) $storeId)
                  ->orWhereJsonContains('store_ids', (int) $storeId);
            })
            ->findOrFail($id);

        // Prevent deactivating oneself
        if ($user->id === auth('api')->id()) {
            return response()->json(['success' => false, 'message' => 'Cannot deactivate yourself.'], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Staff status updated.',
            'is_active' => $user->is_active
        ]);
    }
}
