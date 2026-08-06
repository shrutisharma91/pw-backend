<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next, ...$allowedRoles)
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        $role = strtolower((string) $user->role);
        $normalizedAllowedRoles = array_map(
            static fn (string $value): string => strtolower(trim($value)),
            $allowedRoles
        );

        if (in_array($role, ['superadmin', 'super_admin'], true)) {
            return $next($request);
        }

        if (!in_array($role, $normalizedAllowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not allowed to access this portal.',
                'code'    => 'portal_access_denied',
            ], 403);
        }

        return $next($request);
    }
}
