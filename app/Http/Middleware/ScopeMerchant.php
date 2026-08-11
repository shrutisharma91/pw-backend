<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Resolves scoped merchant_id for /api/v1/merchant/*
 */
class ScopeMerchant
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

        if (in_array($role, ['superadmin', 'super_admin', 'super admin'], true)) {
            $merchantId = $request->query('merchant_id');

            if (!$merchantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin must pass merchant_id query parameter on merchant APIs.',
                    'code'    => 'merchant_id_required',
                ], 422);
            }

            $request->attributes->set('scoped_merchant_id', (int) $merchantId);

            return $next($request);
        }

        if (in_array($role, ['merchant_admin', 'store_manager'], true)) {
            if (!$user->merchant_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Merchant account is not linked to a merchant entity.',
                    'code'    => 'merchant_id_missing',
                ], 403);
            }

            $request->attributes->set('scoped_merchant_id', (int) $user->merchant_id);

            // If it's a store manager, we also might want to scope by store, but for now we'll at least set merchant.
            if ($role === 'store_manager' && $user->store_id) {
                $request->attributes->set('scoped_store_id', (int) $user->store_id);
            }

            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to access the merchant API.',
            'code'    => 'merchant_api_forbidden',
        ], 403);
    }
}
