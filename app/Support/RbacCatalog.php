<?php

namespace App\Support;

class RbacCatalog
{
    public static function guard(): string
    {
        return config('rbac.guard', 'api');
    }

    /** @return list<string> */
    public static function builtinRoles(): array
    {
        return config('rbac.builtin_roles', []);
    }

    /** @return array<string, list<string>> */
    public static function modules(): array
    {
        return config('rbac.modules', []);
    }

    /** @return list<string> */
    public static function allPermissionNames(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::modules()))));
    }

    public static function moduleForPermission(string $permission): string
    {
        foreach (self::modules() as $module => $permissions) {
            if (in_array($permission, $permissions, true)) {
                return $module;
            }
        }

        return 'Other';
    }

    public static function isBuiltinRole(string $name): bool
    {
        return in_array($name, self::builtinRoles(), true);
    }
}
