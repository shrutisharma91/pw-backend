<?php

namespace Database\Seeders;

use App\Models\RoleConfig;
use App\Support\RbacCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = RbacCatalog::guard();

        foreach (RbacCatalog::allPermissionNames() as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        foreach (RbacCatalog::builtinRoles() as $roleName) {
            $role = Role::findOrCreate($roleName, $guard);

            RoleConfig::firstOrCreate(
                ['role_name' => $roleName],
                [
                    'allowlist'                => [],
                    'denylist'                 => [],
                    'concurrent_session_limit' => 5,
                ]
            );

            if (in_array($roleName, ['superadmin', 'super_admin'], true)) {
                $role->syncPermissions(Permission::where('guard_name', $guard)->get());
            }
        }

        $this->command?->info('RBAC permissions and built-in roles synced.');
    }
}
