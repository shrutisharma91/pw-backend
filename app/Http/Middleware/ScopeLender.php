<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Resolves scoped lender_id for /api/v1/lender/* (§1.4).
 */
class ScopeLender
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

        if (in_array($role, ['superadmin', 'super_admin'], true)) {
            $lenderId = $request->query('lender_id');

            if (!$lenderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin must pass lender_id query parameter on lender APIs.',
                    'code'    => 'lender_id_required',
                ], 422);
            }

            $request->attributes->set('scoped_lender_id', (int) $lenderId);

            return $next($request);
        }

        if ($role === 'lender_ops') {
            if (!$user->lender_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lender account is not linked to a lender entity.',
                    'code'    => 'lender_id_missing',
                ], 403);
            }

            $request->attributes->set('scoped_lender_id', (int) $user->lender_id);

            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to access the lender API.',
            'code'    => 'lender_api_forbidden',
        ], 403);
    }
}
