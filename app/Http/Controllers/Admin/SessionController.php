<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSession;
use App\Models\User;
use App\Models\RoleConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| SessionController
|--------------------------------------------------------------------------
| Screen 13 — Session & Device Management
|
| APIs:
|   GET  /api/v1/admin/sessions                        → all active sessions
|   POST /api/v1/admin/sessions/{id}/revoke            → force logout one session
|   POST /api/v1/admin/sessions/users/{userId}/revoke-all → logout all sessions for a user
|   GET  /api/v1/admin/sessions/suspicious             → flagged sessions
|   PUT  /api/v1/admin/sessions/ip-rules               → update IP allowlist/denylist
*/

class SessionController extends Controller
{
    // ------------------------------------------------------------------
    // GET /api/v1/admin/sessions
    // All active sessions — user, role, IP, device, login time
    // ?user_id=1&role=merchant_admin&suspicious=true
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $query = AdminSession::with('user:id,name,email,role')
            ->where('is_active', true);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->boolean('suspicious')) {
            $query->where('is_suspicious', true);
        }

        if ($request->filled('device_type')) {
            $query->where('device_type', $request->device_type);
        }

        $sessions = $query->orderBy('logged_in_at', 'desc')->paginate(25);

        return response()->json([
            'success' => true,
            'data'    => $sessions->map(function ($session) {
                return [
                    'id'               => $session->id,
                    'user'             => $session->user ? [
                        'id'    => $session->user->id,
                        'name'  => $session->user->name,
                        'email' => $session->user->email,
                        'role'  => $session->user->role,
                    ] : null,
                    'ip_address'       => $session->ip_address,
                    'device_type'      => $session->device_type,
                    'device_info'      => $session->device_info,
                    'location'         => $session->location,
                    'is_suspicious'    => $session->is_suspicious,
                    'suspicious_reason'=> $session->suspicious_reason,
                    'logged_in_at'     => $session->logged_in_at,
                    'last_active_at'   => $session->last_active_at,
                ];
            }),
            'meta' => [
                'total'        => $sessions->total(),
                'current_page' => $sessions->currentPage(),
                'last_page'    => $sessions->lastPage(),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/sessions/{id}/revoke
    // Force logout a single session
    // ------------------------------------------------------------------
    public function revoke(Request $request, int $id)
    {
        $session = AdminSession::findOrFail($id);

        $session->update([
            'is_active'     => false,
            'logged_out_at' => now(),
        ]);

        Log::warning("Session {$id} force-revoked by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Session revoked. User will be logged out on next request.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/sessions/users/{userId}/revoke-all
    // Logout ALL sessions for a specific user
    // ------------------------------------------------------------------
    public function revokeAll(Request $request, int $userId)
    {
        $user = User::findOrFail($userId);

        $count = AdminSession::where('user_id', $userId)
            ->where('is_active', true)
            ->count();

        AdminSession::where('user_id', $userId)
            ->where('is_active', true)
            ->update([
                'is_active'     => false,
                'logged_out_at' => now(),
            ]);

        Log::warning("All {$count} sessions for user {$userId} revoked by admin " . auth()->id());

        return response()->json([
            'success'          => true,
            'message'          => "All {$count} sessions for {$user->name} have been revoked.",
            'sessions_revoked' => $count,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/sessions/suspicious
    // Sessions flagged as suspicious
    // ------------------------------------------------------------------
    public function suspicious()
    {
        $sessions = AdminSession::with('user:id,name,email,role')
            ->where('is_suspicious', true)
            ->where('is_active', true)
            ->orderBy('logged_in_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $sessions->count(),
            'data'    => $sessions->map(function ($session) {
                return [
                    'id'               => $session->id,
                    'user'             => $session->user,
                    'ip_address'       => $session->ip_address,
                    'device_type'      => $session->device_type,
                    'suspicious_reason'=> $session->suspicious_reason,
                    'logged_in_at'     => $session->logged_in_at,
                ];
            }),
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/sessions/ip-rules
    // Update IP allowlist and denylist per role
    // Body: { "role": "merchant_admin", "allowlist": ["192.168.1.0/24"], "denylist": ["10.0.0.5"] }
    // ------------------------------------------------------------------
    public function updateIPRules(Request $request)
    {
        $request->validate([
            'role'                     => 'required|string',
            'allowlist'                => 'sometimes|array',
            'denylist'                 => 'sometimes|array',
            'concurrent_session_limit' => 'sometimes|integer|min:1|max:100',
        ]);

        // Save persistently to database role_configs table
        $roleConfig = RoleConfig::firstOrCreate(
            ['role_name' => $request->role],
            [
                'allowlist' => [],
                'denylist' => [],
                'concurrent_session_limit' => 5
            ]
        );

        if ($request->has('allowlist')) {
            $roleConfig->allowlist = $request->allowlist;
        }
        if ($request->has('denylist')) {
            $roleConfig->denylist = $request->denylist;
        }
        if ($request->has('concurrent_session_limit')) {
            $roleConfig->concurrent_session_limit = $request->concurrent_session_limit;
        }

        $roleConfig->save();

        Log::info("IP rules and session configurations updated for role '{$request->role}' by admin " . auth()->id());

        return response()->json([
            'success'  => true,
            'message'  => "Configurations updated for role '{$request->role}' persistently in database.",
            'config'    => [
                'role_name' => $roleConfig->role_name,
                'allowlist' => $roleConfig->allowlist,
                'denylist' => $roleConfig->denylist,
                'concurrent_session_limit' => $roleConfig->concurrent_session_limit,
            ],
        ]);
    }
}