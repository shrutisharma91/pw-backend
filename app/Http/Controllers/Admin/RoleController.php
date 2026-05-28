<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| RoleController
|--------------------------------------------------------------------------
| Screen 11 — Role Management
| Screen 12 — Permission Matrix
|
| Uses Spatie Laravel Permission package.
| All roles and permissions are stored in the spatie tables.
*/

class RoleController extends Controller
{
    // ------------------------------------------------------------------
    // GET /api/v1/admin/roles
    // Screen 11 — List all roles with user count
    // ------------------------------------------------------------------
    public function index()
    {
        $roles = Role::withCount('users')
            ->where('guard_name', 'api')
            ->get()
            ->map(function ($role) {
                return [
                    'id'          => $role->id,
                    'name'        => $role->name,
                    'guard_name'  => $role->guard_name,
                    'user_count'  => $role->users_count,
                    'permissions' => $role->permissions->pluck('name'),
                    'created_at'  => $role->created_at,
                    'updated_at'  => $role->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $roles,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/roles/{id}
    // Screen 11 — Single role detail
    // ------------------------------------------------------------------
    public function show(int $id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'user_count'  => $role->users()->count(),
                'users'       => $role->users()->limit(10)->get(['id', 'name', 'email']),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/roles
    // Screen 11 — Create new role
    // Body: { "name": "finance_ops", "permissions": ["view_loans", "export_reports"] }
    // ------------------------------------------------------------------
    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:100|unique:roles,name',
            'permissions'   => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name'       => $request->name,
            'guard_name' => 'api',
        ]);

        if ($request->filled('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        Log::info("Role '{$role->name}' created by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' created successfully.",
            'data'    => ['id' => $role->id, 'name' => $role->name],
        ], 201);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/roles/{id}
    // Screen 11 — Edit role name
    // ------------------------------------------------------------------
    public function update(Request $request, int $id)
    {
        $role = Role::findOrFail($id);

        // Prevent editing built-in roles
        $builtIn = ['super_admin', 'merchant_admin', 'store_manager', 'sales_exec', 'lender_ops', 'risk_user', 'customer'];
        if (in_array($role->name, $builtIn)) {
            return response()->json([
                'success' => false,
                'message' => 'Built-in roles cannot be renamed.',
            ], 422);
        }

        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,' . $id,
        ]);

        $role->update(['name' => $request->name]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated.',
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/roles/{id}/clone
    // Screen 11 — Clone a role with all its permissions
    // Body: { "new_name": "merchant_admin_readonly" }
    // ------------------------------------------------------------------
    public function clone(Request $request, int $id)
    {
        $request->validate([
            'new_name' => 'required|string|max:100|unique:roles,name',
        ]);

        $sourceRole = Role::with('permissions')->findOrFail($id);

        $newRole = Role::create([
            'name'       => $request->new_name,
            'guard_name' => 'api',
        ]);

        // Copy all permissions from source role
        $newRole->syncPermissions($sourceRole->permissions);

        Log::info("Role '{$sourceRole->name}' cloned as '{$newRole->name}' by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => "Role cloned as '{$newRole->name}'.",
            'data'    => [
                'id'              => $newRole->id,
                'name'            => $newRole->name,
                'cloned_from'     => $sourceRole->name,
                'permissions_copied' => $sourceRole->permissions->count(),
            ],
        ], 201);
    }

    // ------------------------------------------------------------------
    // DELETE /api/v1/admin/roles/{id}
    // Screen 11 — Archive (soft delete) a role
    // ------------------------------------------------------------------
    public function archive(Request $request, int $id)
    {
        $role = Role::findOrFail($id);

        $builtIn = ['super_admin', 'merchant_admin', 'store_manager', 'sales_exec', 'lender_ops', 'risk_user', 'customer'];
        if (in_array($role->name, $builtIn)) {
            return response()->json([
                'success' => false,
                'message' => 'Built-in roles cannot be archived.',
            ], 422);
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return response()->json([
                'success'    => false,
                'message'    => "Cannot archive role '{$role->name}'. {$userCount} users are assigned to it. Reassign them first.",
                'user_count' => $userCount,
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' archived.",
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/permissions
    // Screen 12 — Full permission matrix (all roles x all permissions)
    // ------------------------------------------------------------------
    public function permissionMatrix()
    {
        $roles       = Role::with('permissions')->where('guard_name', 'api')->get();
        $permissions = Permission::where('guard_name', 'api')->get();

        // Group permissions by module
        $modules = [
            'Authentication'  => ['login', 'logout'],
            'Profile'         => ['view_profile', 'edit_profile', 'change_password', 'setup_mfa'],
            'Notifications'   => ['view_notifications', 'manage_notifications'],
            'Users'           => ['view_users', 'create_users', 'edit_users', 'delete_users'],
            'Roles'           => ['view_roles', 'create_roles', 'edit_roles', 'delete_roles'],
            'Sessions'        => ['view_sessions', 'revoke_sessions'],
        ];

        $matrix = [];
        foreach ($modules as $module => $modulePermissions) {
            $row = ['module' => $module, 'permissions' => []];
            foreach ($modulePermissions as $permName) {
                $permRow = ['permission' => $permName, 'roles' => []];
                foreach ($roles as $role) {
                    $permRow['roles'][$role->name] = $role->permissions->contains('name', $permName);
                }
                $row['permissions'][] = $permRow;
            }
            $matrix[] = $row;
        }

        return response()->json([
            'success'     => true,
            'roles'       => $roles->pluck('name'),
            'matrix'      => $matrix,
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/permissions/roles/{id}
    // Screen 12 — Update permissions for a role
    // Body: { "permissions": ["view_users", "create_users"] }
    // ------------------------------------------------------------------
    public function updatePermissions(Request $request, int $id)
    {
        $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::findOrFail($id);

        // Save old permissions for audit/rollback
        $oldPermissions = $role->permissions->pluck('name')->toArray();

        // Persistent database audit trail with snapshot backup
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_permissions_update',
            'module' => 'roles',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'old_permissions' => $oldPermissions,
                'new_permissions' => $request->permissions,
            ],
        ]);

        // Apply new permissions
        $role->syncPermissions($request->permissions);

        Log::info("Permissions updated for role '{$role->name}' by admin " . auth()->id());

        return response()->json([
            'success'         => true,
            'message'         => "Permissions updated for '{$role->name}'.",
            'old_permissions' => $oldPermissions,
            'new_permissions' => $request->permissions,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/permissions/roles/{id}/diff/{compareId}
    // Screen 12 — Compare permissions of two roles side by side
    // ------------------------------------------------------------------
    public function diffRoles(int $id, int $compareId)
    {
        $role1 = Role::with('permissions')->findOrFail($id);
        $role2 = Role::with('permissions')->findOrFail($compareId);

        $perms1 = $role1->permissions->pluck('name')->toArray();
        $perms2 = $role2->permissions->pluck('name')->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'role1' => [
                    'name'        => $role1->name,
                    'permissions' => $perms1,
                ],
                'role2' => [
                    'name'        => $role2->name,
                    'permissions' => $perms2,
                ],
                'only_in_role1' => array_values(array_diff($perms1, $perms2)),
                'only_in_role2' => array_values(array_diff($perms2, $perms1)),
                'common'        => array_values(array_intersect($perms1, $perms2)),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/permissions/roles/{id}/rollback
    // Screen 12 — Rollback to previous permission matrix
    // ------------------------------------------------------------------
    public function rollback(int $id)
    {
        // Find latest update audit log record for this role to get backup snapshot
        $latestAudit = AuditLog::where('action', 'role_permissions_update')
            ->where('module', 'roles')
            ->where('payload->role_id', $id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$latestAudit || !isset($latestAudit->payload['old_permissions'])) {
            return response()->json([
                'success' => false,
                'message' => 'No previous database-backed backup found for this role.',
            ], 404);
        }

        $role = Role::findOrFail($id);
        $restoredPermissions = $latestAudit->payload['old_permissions'];
        
        // Log rollback event in DB
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'role_permissions_rollback',
            'module' => 'roles',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'payload' => [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'restored_permissions' => $restoredPermissions,
                'rolled_back_from_audit_id' => $latestAudit->id,
            ],
        ]);

        $role->syncPermissions($restoredPermissions);

        Log::info("Permissions ROLLED BACK for role '{$role->name}' by admin " . auth()->id());

        return response()->json([
            'success'             => true,
            'message'             => "Permissions rolled back to previous state.",
            'restored_permissions' => $restoredPermissions,
            'backup_was_from'     => $latestAudit->created_at->toISOString(),
        ]);
    }
}