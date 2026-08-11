<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Resolves scoped store_id for /api/v1/pos/*
 */
class ScopeStore
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $role = strtolower((string) $user->role);

        if (in_array($role, ['store_manager'], true)) {
            // Check if store_ids array exists or store_id (if we used merchant_id instead in the DB)
            // Wait, we saw in User model: 'store_ids' (JSON array) and in ScopeMerchant: $user->store_id
            // Let's rely on the user having at least one store ID.
            $storeIds = is_array($user->store_ids) ? $user->store_ids : [];
            
            // For now, if no store_ids array, check if a single store_id exists just in case
            if (empty($storeIds) && !empty($user->store_id)) {
                $storeIds = [$user->store_id];
            }

            if (empty($storeIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is not linked to any store.',
                    'code'    => 'store_id_missing',
                ], 403);
            }

            // We default to the first store, or expect it in the header for multi-store managers
            $requestedStore = $request->header('X-Store-ID');
            $activeStoreId = $requestedStore && in_array($requestedStore, $storeIds) ? $requestedStore : $storeIds[0];

            $request->attributes->set('scoped_store_id', (int) $activeStoreId);
            $request->attributes->set('scoped_merchant_id', (int) $user->merchant_id);

            return $next($request);
        }

        // For Super Admin / Merchant Admin (View as Mode)
        if (in_array($role, ['superadmin', 'super_admin', 'super admin', 'merchant_admin'], true)) {
            $storeId = $request->query('store_id');
            $merchantId = $request->query('merchant_id') ?? $user->merchant_id; // Use own merchant ID if merchant admin

            if (!$storeId || !$merchantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin must pass store_id and merchant_id query parameters on POS APIs.',
                    'code'    => 'store_id_required',
                ], 422);
            }

            $request->attributes->set('scoped_store_id', (int) $storeId);
            $request->attributes->set('scoped_merchant_id', (int) $merchantId);

            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to access the POS API.',
            'code'    => 'pos_api_forbidden',
        ], 403);
    }
}
