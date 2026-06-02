<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RoleConfig;
use App\Models\User;
use App\Support\RbacCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private function apiGuard(): string
    {
        return RbacCatalog::guard();
    }

    private function findApiRole(int $id): Role
    {
        return Role::with('permissions')
            ->where('guard_name', $this->apiGuard())
            ->findOrFail($id);
    }

    /** @return array<int, int> */
    private function userCountsByRole(): array
    {
        return DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->selectRaw('role_id, COUNT(*) as aggregate')
            ->groupBy('role_id')
            ->pluck('aggregate', 'role_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function formatRole(Role $role, array $userCounts): array
    {
        return [
            'id'           => $role->id,
            'name'         => $role->name,
            'guard_name'   => $role->guard_name,
            'is_builtin'   => RbacCatalog::isBuiltinRole($role->name),
            'user_count'   => $userCounts[$role->id] ?? 0,
            'permissions'  => $role->permissions->pluck('name')->values(),
            'created_at'   => $role->created_at,
            'updated_at'   => $role->updated_at,
        ];
    }

    /** @param list<string> $names */
    private function syncRolePermissions(Role $role, array $names): void
    {
        $permissions = collect($names)->map(
            fn (string $name) => Permission::findOrCreate($name, $this->apiGuard())
        );

        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/roles
    // ------------------------------------------------------------------
    public function index()
    {
        $userCounts = $this->userCountsByRole();

        $roles = Role::with('permissions')
            ->where('guard_name', $this->apiGuard())
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->formatRole($role, $userCounts));

        return response()->json([
            'success' => true,
            'data'    => $roles,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/roles/{id}
    // ------------------------------------------------------------------
    public function show(int $id)
    {
        $role = $this->findApiRole($id);
        $userCounts = $this->userCountsByRole();

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->formatRole($role, $userCounts), [
                'users' => $role->users()->limit(10)->get(['id', 'name', 'email']),
            ]),
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/roles
    // ------------------------------------------------------------------
    public function store(Request $request)
    {
        $guard = $this->apiGuard();

        $request->validate([
            'name'          => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', $guard),
                Rule::notIn(RbacCatalog::builtinRoles()),
            ],
            'permissions'   => 'sometimes|array',
            'permissions.*' => ['string', Rule::in(RbacCatalog::allPermissionNames())],
        ], [
            'name.regex'    => 'Role name must be lowercase letters, numbers, and underscores (e.g. finance_ops).',
            'name.not_in'   => 'This name is reserved for a built-in role.',
        ]);

        $role = Role::create([
            'name'       => $request->name,
            'guard_name' => $guard,
        ]);

        if ($request->filled('permissions')) {
            $this->syncRolePermissions($role, $request->permissions);
        }

        RoleConfig::firstOrCreate(
            ['role_name' => $role->name],
            [
                'allowlist'                => [],
                'denylist'                 => [],
                'concurrent_session_limit' => 5,
            ]
        );

        $role->load('permissions');

        Log::info("Custom role '{$role->name}' created by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' created successfully.",
            'data'    => $this->formatRole($role, $this->userCountsByRole()),
        ], 201);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/roles/{id}
    // ------------------------------------------------------------------
    public function update(Request $request, int $id)
    {
        $role = $this->findApiRole($id);
        $guard = $this->apiGuard();

        if (RbacCatalog::isBuiltinRole($role->name)) {
            return response()->json([
                'success' => false,
                'message' => 'Built-in roles cannot be renamed.',
            ], 422);
        }

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', $guard)->ignore($role->id),
                Rule::notIn(RbacCatalog::builtinRoles()),
            ],
        ]);

        $oldName = $role->name;
        $role->update(['name' => $request->name]);

        RoleConfig::where('role_name', $oldName)->update(['role_name' => $request->name]);

        Log::info("Role '{$oldName}' renamed to '{$request->name}' by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Role updated.',
            'data'    => $this->formatRole($role->fresh()->load('permissions'), $this->userCountsByRole()),
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/roles/{id}/clone
    // ------------------------------------------------------------------
    public function clone(Request $request, int $id)
    {
        $guard = $this->apiGuard();
        $sourceRole = $this->findApiRole($id);

        $request->validate([
            'new_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', $guard),
                Rule::notIn(RbacCatalog::builtinRoles()),
            ],
        ]);

        $newRole = Role::create([
            'name'       => $request->new_name,
            'guard_name' => $guard,
        ]);

        $newRole->syncPermissions($sourceRole->permissions);

        RoleConfig::firstOrCreate(
            ['role_name' => $newRole->name],
            [
                'allowlist'                => [],
                'denylist'                 => [],
                'concurrent_session_limit' => 5,
            ]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newRole->load('permissions');

        Log::info("Role '{$sourceRole->name}' cloned as '{$newRole->name}' by admin " . auth()->id());

        return response()->json([
            'success' => true,
            'message' => "Role cloned as '{$newRole->name}'.",
            'data'    => [
                'cloned_from'        => $sourceRole->name,
                'permissions_copied' => $sourceRole->permissions->count(),
                'role'               => $this->formatRole($newRole, $this->userCountsByRole()),
            ],
        ], 201);
    }

    // ------------------------------------------------------------------
    // DELETE /api/v1/admin/roles/{id}
    // ------------------------------------------------------------------
    public function archive(int $id)
    {
        $role = $this->findApiRole($id);

        if (RbacCatalog::isBuiltinRole($role->name)) {
            return response()->json([
                'success' => false,
                'message' => 'Built-in roles cannot be archived.',
            ], 422);
        }

        $userCount = (int) DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', User::class)
            ->count();

        if ($userCount > 0) {
            return response()->json([
                'success'    => false,
                'message'    => "Cannot archive role '{$role->name}'. {$userCount} users are assigned to it. Reassign them first.",
                'user_count' => $userCount,
            ], 422);
        }

        $roleName = $role->name;
        $role->delete();
        RoleConfig::where('role_name', $roleName)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => "Role '{$roleName}' archived.",
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/permissions
    // ------------------------------------------------------------------
    public function permissionMatrix()
    {
        $roles = Role::with('permissions')
            ->where('guard_name', $this->apiGuard())
            ->orderBy('name')
            ->get();

        $permissions = Permission::where('guard_name', $this->apiGuard())
            ->orderBy('name')
            ->get();

        $matrix = [];

        foreach (RbacCatalog::modules() as $module => $modulePermissionNames) {
            $row = ['module' => $module, 'permissions' => []];

            foreach ($modulePermissionNames as $permName) {
                $permission = $permissions->firstWhere('name', $permName);

                if (!$permission) {
                    continue;
                }

                $permRow = ['permission' => $permName, 'roles' => []];

                foreach ($roles as $role) {
                    $permRow['roles'][$role->name] = $role->permissions->contains('name', $permName);
                }

                $row['permissions'][] = $permRow;
            }

            if (!empty($row['permissions'])) {
                $matrix[] = $row;
            }
        }

        return response()->json([
            'success'     => true,
            'roles'       => $roles->pluck('name')->values(),
            'permissions' => $permissions->map(fn (Permission $p) => [
                'name'   => $p->name,
                'module' => RbacCatalog::moduleForPermission($p->name),
            ])->values(),
            'matrix'      => $matrix,
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/permissions/roles/{id}
    // ------------------------------------------------------------------
    public function updatePermissions(Request $request, int $id)
    {
        $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => ['string', Rule::in(RbacCatalog::allPermissionNames())],
        ]);

        $role = $this->findApiRole($id);
        $oldPermissions = $role->permissions->pluck('name')->toArray();

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'role_permissions_update',
            'module'     => 'roles',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload'    => [
                'role_id'           => $role->id,
                'role_name'         => $role->name,
                'old_permissions'   => $oldPermissions,
                'new_permissions'   => $request->permissions,
            ],
            'created_at' => now(),
        ]);

        $this->syncRolePermissions($role, $request->permissions);

        Log::info("Permissions updated for role '{$role->name}' by admin " . auth()->id());

        return response()->json([
            'success'         => true,
            'message'         => "Permissions updated for '{$role->name}'.",
            'data'            => $this->formatRole($role->fresh()->load('permissions'), $this->userCountsByRole()),
            'old_permissions' => $oldPermissions,
            'new_permissions' => $request->permissions,
        ]);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/admin/permissions/roles/{id}/diff/{compareId}
    // ------------------------------------------------------------------
    public function diffRoles(int $id, int $compareId)
    {
        $role1 = $this->findApiRole($id);
        $role2 = $this->findApiRole($compareId);

        $perms1 = $role1->permissions->pluck('name')->toArray();
        $perms2 = $role2->permissions->pluck('name')->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'role1'         => ['id' => $role1->id, 'name' => $role1->name, 'permissions' => $perms1],
                'role2'         => ['id' => $role2->id, 'name' => $role2->name, 'permissions' => $perms2],
                'only_in_role1' => array_values(array_diff($perms1, $perms2)),
                'only_in_role2' => array_values(array_diff($perms2, $perms1)),
                'common'        => array_values(array_intersect($perms1, $perms2)),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/admin/permissions/roles/{id}/rollback
    // ------------------------------------------------------------------
    public function rollback(int $id)
    {
        $role = $this->findApiRole($id);

        $latestAudit = AuditLog::where('action', 'role_permissions_update')
            ->where('module', 'roles')
            ->where('payload->role_id', $role->id)
            ->orderByDesc('id')
            ->first();

        if (!$latestAudit || empty($latestAudit->payload['old_permissions'])) {
            return response()->json([
                'success' => false,
                'message' => 'No previous permission snapshot found for this role.',
            ], 404);
        }

        $restoredPermissions = $latestAudit->payload['old_permissions'];

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'role_permissions_rollback',
            'module'     => 'roles',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'payload'    => [
                'role_id'                => $role->id,
                'role_name'              => $role->name,
                'restored_permissions'   => $restoredPermissions,
                'rolled_back_from_audit' => $latestAudit->id,
            ],
            'created_at' => now(),
        ]);

        $this->syncRolePermissions($role, $restoredPermissions);

        Log::info("Permissions rolled back for role '{$role->name}' by admin " . auth()->id());

        return response()->json([
            'success'              => true,
            'message'              => 'Permissions rolled back to previous state.',
            'data'                 => $this->formatRole($role->fresh()->load('permissions'), $this->userCountsByRole()),
            'restored_permissions' => $restoredPermissions,
            'backup_was_from'      => $latestAudit->created_at?->toIso8601String(),
        ]);
    }
}
