<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAgentApiRole
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
        $allowed = ['sales_exec', 'superadmin', 'super_admin'];

        if (!in_array($role, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Agent API access denied for this role.',
                'code'    => 'agent_api_role_denied',
            ], 403);
        }

        return $next($request);
    }
}
