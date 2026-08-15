<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves scoped sales_exec_id for /api/v1/agent/*
 */
class ScopeAgent
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
            $salesExecId = $request->query('sales_exec_id');

            if (!$salesExecId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin must pass sales_exec_id query parameter on agent APIs.',
                    'code'    => 'sales_exec_id_required',
                ], 422);
            }

            $agent = User::query()
                ->where('id', $salesExecId)
                ->where('role', 'sales_exec')
                ->first();

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sales executive not found.',
                    'code'    => 'sales_exec_not_found',
                ], 404);
            }

            $request->attributes->set('scoped_sales_exec_id', (int) $agent->id);

            return $next($request);
        }

        if ($role === 'sales_exec') {
            $request->attributes->set('scoped_sales_exec_id', (int) $user->id);

            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to access the agent API.',
            'code'    => 'agent_api_forbidden',
        ], 403);
    }
}
