<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks non–Super Admin roles from /api/v1/admin/* (§1.1).
 */
class EnsureSuperAdminOnly
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

        if (!in_array($role, ['superadmin', 'super_admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Super Admin API only.',
                'code'    => 'admin_api_forbidden',
            ], 403);
        }

        return $next($request);
    }
}
