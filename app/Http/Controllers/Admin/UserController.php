<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCode;
use App\Models\User;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| UserController
|--------------------------------------------------------------------------
| Screen 09 — User Directory
| Screen 10 — Create / Edit User
|
| Every user on the platform is created and managed by Super Admin.
| No self-registration allowed.
*/

class UserController extends Controller
{
    private function ensureSuperAdmin(): ?JsonResponse
    {
        /** @var User|null $admin */
        $admin = Auth::user();

        if (!in_array($admin->role, ['superadmin', 'super_admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only Super Admin can perform this action.',
            ], 403);
        }

        return null;
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/users
    // Screen 09 — List all users with filters
    // ?role=merchant_admin&status=active&search=rajesh&page=1
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $query = User::query();

        // Filter by role
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by merchant
        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        // Search by name, email, mobile
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('mobile', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by last login
        if ($request->filled('last_login_from')) {
            $query->where('last_login_at', '>=', $request->last_login_from);
        }

        $users = $query->orderBy('created_at', 'desc')
                       ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $users->map(function ($user) {
                return [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'mobile'        => $user->mobile,
                    'role'          => $user->role,
                    'merchant_id'   => $user->merchant_id,
                    'status'        => $user->is_active ? 'active' : 'disabled',
                    'mfa_enabled'   => $user->mfa_enabled,
                    'last_login_at' => $user->last_login_at,
                    'created_at'    => $user->created_at,
                ];
            }),
            'meta' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/users/{id}
    // Screen 10 — Get single user for edit form
    // ------------------------------------------------------------------
    public function show(int $id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'mobile'            => $user->mobile,
                'role'              => $user->role,
                'roles'             => $user->getRoleNames(),
                'permissions'       => $user->getAllPermissions()->pluck('name'),
                'merchant_id'       => $user->merchant_id,
                'store_ids'         => $user->store_ids,
                'mfa_enabled'       => $user->mfa_enabled,
                'mfa_channel'       => $user->mfa_channel,
                'is_active'         => $user->is_active,
                'last_login_at'     => $user->last_login_at,
                'last_login_ip'     => $user->last_login_ip,
                'created_at'        => $user->created_at,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/users
    // Screen 10 — Create new user and send invite email
    // ------------------------------------------------------------------
    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|unique:users,email',
            'mobile'      => 'required|string|size:10|unique:users,mobile',
            'role'        => 'required|in:merchant_admin,store_manager,sales_exec,lender_ops,risk_user,customer',
            'merchant_id' => 'nullable|integer',
            'store_ids'   => 'nullable|array',
            'mfa_enabled' => 'sometimes|boolean',
        ]);

        // Generate a temporary password
        $tempPassword = Str::random(12) . '@1';

        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'mobile'      => $request->mobile,
            'password'    => Hash::make($tempPassword),
            'role'        => $request->role,
            'merchant_id' => $request->merchant_id,
            'store_ids'   => $request->store_ids,
            'mfa_enabled' => $request->get('mfa_enabled', false),
            'mfa_channel' => 'email',
            'is_active'   => true,
            'notification_channels' => [
                'in_app' => true, 'email' => true, 'sms' => false, 'whatsapp' => false,
            ],
        ]);

        // Assign role via Spatie
        $user->assignRole($request->role);

        // Log action
        Log::info("User {$user->id} ({$user->email}) created by admin " . auth()->id());

        // Send invite email with temp password
        $this->sendInviteEmail($user, $tempPassword);

        return response()->json([
            'success' => true,
            'message' => 'User created and invite sent to ' . $user->email,
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ], 201);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/users/{id}
    // Screen 10 — Edit existing user
    // ------------------------------------------------------------------
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'        => 'sometimes|string|max:100',
            'mobile'      => 'sometimes|string|size:10|unique:users,mobile,' . $id,
            'role'        => 'sometimes|in:merchant_admin,store_manager,sales_exec,lender_ops,risk_user,customer',
            'merchant_id' => 'nullable|integer',
            'store_ids'   => 'nullable|array',
            'mfa_enabled' => 'sometimes|boolean',
        ]);

        $updateData = $request->only([
            'name', 'mobile', 'merchant_id', 'store_ids', 'mfa_enabled',
        ]);

        // Update role if changed
        if ($request->filled('role') && $request->role !== $user->role) {
            $user->syncRoles([$request->role]);
            $updateData['role'] = $request->role;
        }

        $user->update($updateData);

        Log::info("User {$user->id} updated by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/users/{id}/disable
    // ------------------------------------------------------------------
    public function disable(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $user = User::findOrFail($id);

        // Prevent disabling yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot disable your own account.',
            ], 422);
        }

        $user->update(['is_active' => false]);

        // Revoke all active sessions
        $user->sessions()->where('is_active', true)->update([
            'is_active'     => false,
            'logged_out_at' => now(),
        ]);

        Log::warning("User {$user->id} DISABLED by admin " . auth()->id() . ". Reason: {$request->reason}");

        return response()->json([
            'success' => true,
            'message' => 'User disabled and all sessions revoked.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/users/{id}/enable
    // ------------------------------------------------------------------
    public function enable(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        Log::info("User {$user->id} ENABLED by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'User enabled successfully.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/users/{id}/reset-password
    // Super Admin requests a verification code to reset any user's password
    // ------------------------------------------------------------------
    public function resetPassword(Request $request, int $id)
    {
        if ($denied = $this->ensureSuperAdmin()) {
            return $denied;
        }

        /** @var User|null $admin */
        $admin = Auth::user();
        $targetUser = User::findOrFail($id);

        $request->validate([
            'email' => 'required|email',
        ]);

        if ($admin->email !== $request->email) {
            return response()->json([
                'success' => false,
                'message' => 'Email must match your Super Admin login email.',
            ], 422);
        }

        DB::table('password_reset_tokens')
            ->where('email', $admin->email)
            ->delete();

        $code = sprintf('%06d', random_int(0, 999999));

        DB::table('password_reset_tokens')->insert([
            'email'      => $admin->email,
            'token'      => Hash::make($code),
            'created_at' => now()->toIso8601String(),
        ]);

        Cache::put(
            'admin_user_password_reset:' . $admin->id,
            $targetUser->id,
            now()->addMinutes(15)
        );

        Mail::to($admin->email)->send(new PasswordResetCode($admin->name, $code));

        Log::info("Password reset verification code sent to super admin {$admin->id} for target user {$targetUser->id}");

        return response()->json([
            'success'            => true,
            'message'            => 'Verification code sent to ' . $admin->email,
            'target_user_id'     => $targetUser->id,
            'target_user_email'  => $targetUser->email,
            'expires_in_minutes' => 15,
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/users/{id}/change-password
    // Super Admin sets a new password for any user using the verification code
    // ------------------------------------------------------------------
    public function changePassword(Request $request, int $id)
    {
        if ($denied = $this->ensureSuperAdmin()) {
            return $denied;
        }

        /** @var User|null $admin */
        $admin = Auth::user();
        $targetUser = User::findOrFail($id);

        $cachedTargetId = Cache::get('admin_user_password_reset:' . $admin->id);

        if (!$cachedTargetId || (int) $cachedTargetId !== $id) {
            return response()->json([
                'success' => false,
                'message' => 'No pending password reset for this user. Please request a verification code first.',
            ], 422);
        }

        $request->validate([
            'verification_code'         => 'required|string|size:6',
            'new_password'              => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&]/',
            ],
            'new_password_confirmation' => 'required|string',
        ], [
            'new_password.regex' => 'Password must include uppercase, number, and special character.',
        ]);

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $admin->email)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code. Please request a new one.',
            ], 422);
        }

        if (\Illuminate\Support\Carbon::parse($resetRecord->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $admin->email)->delete();
            Cache::forget('admin_user_password_reset:' . $admin->id);

            return response()->json([
                'success' => false,
                'message' => 'Verification code has expired. Please request a new one.',
                'code'    => 'token_expired',
            ], 422);
        }

        if (!Hash::check($request->verification_code, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $pastPasswords = $targetUser->passwordHistories()->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($pastPasswords as $pastPassword) {
            if (Hash::check($request->new_password, $pastPassword->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot reuse any of your last 5 passwords.',
                    'code'    => 'password_reuse_blocked',
                ], 422);
            }
        }

        $targetUser->update([
            'password'            => Hash::make($request->new_password),
            'password_changed_at' => now(),
        ]);

        $targetUser->passwordHistories()->create([
            'password' => Hash::make($request->new_password),
        ]);

        DB::table('password_reset_tokens')->where('email', $admin->email)->delete();
        Cache::forget('admin_user_password_reset:' . $admin->id);

        $targetUser->sessions()->where('is_active', true)->update([
            'is_active'     => false,
            'logged_out_at' => now(),
        ]);

        Log::info("Password changed for user {$targetUser->id} by super admin {$admin->id}");

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully for ' . $targetUser->email . '.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/users/{id}/force-mfa
    // Force user to set up MFA on next login
    // ------------------------------------------------------------------
    public function forceMFA(int $id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'mfa_enabled'     => true,
            'mfa_verified_at' => null, // invalidate current MFA session
        ]);

        return response()->json([
            'success' => true,
            'message' => 'MFA enforced for ' . $user->name . '. They will be prompted on next login.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/users/bulk-disable
    // Body: { "user_ids": [1, 2, 3], "reason": "Policy violation" }
    // ------------------------------------------------------------------
    public function bulkDisable(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer',
            'reason'   => 'required|string',
        ]);

        // Never disable yourself
        $ids = array_filter($request->user_ids, fn($id) => $id !== auth()->id());

        User::whereIn('id', $ids)->update(['is_active' => false]);

        Log::warning('Bulk disable: users ' . implode(',', $ids) . ' by admin ' . auth()->id());

        return response()->json([
            'success' => true,
            'message' => count($ids) . ' users disabled.',
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/users/export/csv
    // Export user list as CSV
    // ------------------------------------------------------------------
    public function exportCSV(Request $request)
    {
        $users = User::select('id', 'name', 'email', 'mobile', 'role', 'is_active', 'last_login_at', 'created_at')
            ->get();

        $csv = "ID,Name,Email,Mobile,Role,Status,Last Login,Created At\n";
        foreach ($users as $user) {
            $csv .= implode(',', [
                $user->id,
                '"' . $user->name . '"',
                $user->email,
                $user->mobile,
                $user->role,
                $user->is_active ? 'active' : 'disabled',
                $user->last_login_at,
                $user->created_at,
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/users/{id}/impersonate
    // Log into platform as another user (with mandatory audit log)
    // ------------------------------------------------------------------
    public function impersonate(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $targetUser = User::findOrFail($id);

        // Prevent impersonating another super admin
        if ($targetUser->role === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot impersonate another Super Admin.',
            ], 403);
        }

        // Log impersonation — this is mandatory per spec
        Log::critical(
            "IMPERSONATION: Admin " . auth()->id() . " is impersonating user {$targetUser->id} ({$targetUser->email}). " .
            "Reason: {$request->reason}. IP: " . request()->ip()
        );

        // Database audit log
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'impersonation_start',
            'module' => 'users',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => [
                'target_user_id' => $targetUser->id,
                'target_email' => $targetUser->email,
                'reason' => $request->reason,
            ],
        ]);

        // Generate token for target user
        $token = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::fromUser($targetUser);

        return response()->json([
            'success'         => true,
            'message'         => 'Impersonation started. All actions are being logged.',
            'impersonating'   => [
                'id'    => $targetUser->id,
                'name'  => $targetUser->name,
                'email' => $targetUser->email,
                'role'  => $targetUser->role,
            ],
            'token'           => $token,
            'audit_logged'    => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------
    private function sendInviteEmail(User $user, string $tempPassword): void
    {
        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($user, $tempPassword) {
            $message->to($user->email)
                    ->subject('Welcome to FinZ — Your Account Details')
                    ->html("
                        <h2>Welcome to FinZ, {$user->name}!</h2>
                        <p>Your account has been created by the Super Admin.</p>
                        <p><b>Email:</b> {$user->email}</p>
                        <p><b>Temporary Password:</b> {$tempPassword}</p>
                        <p>Please login and change your password immediately.</p>
                        <p>Login URL: " . config('app.frontend_url') . "</p>
                    ");
        });
    }
}