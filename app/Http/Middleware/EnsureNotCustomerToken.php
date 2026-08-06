<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks customer JWT from accessing /api/v1/admin/* (and other non-customer APIs).
 * Customer tokens authenticate against the customers provider — if somehow used with
 * auth:api, this middleware still denies portal-level admin access.
 */
class EnsureNotCustomerToken
{
    public function handle(Request $request, Closure $next)
    {
        // If a customer is authenticated on the customer guard, they must not use admin APIs.
        if (auth('customer')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Customer tokens cannot access Super Admin APIs.',
                'code'    => 'customer_admin_forbidden',
            ], 403);
        }

        return $next($request);
    }
}
