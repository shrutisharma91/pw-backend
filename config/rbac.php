<?php

/*
|--------------------------------------------------------------------------
| RBAC catalog — permissions grouped by module and built-in role names
|--------------------------------------------------------------------------
*/

return [

    'guard' => 'api',

    'builtin_roles' => [
        'superadmin',
        'super_admin',
        'merchant_admin',
        'store_manager',
        'sales_exec',
        'lender_ops',
        'risk_user',
        'customer',
    ],

    'modules' => [
        'Authentication'  => ['login', 'logout'],
        'Profile'         => ['view_profile', 'edit_profile', 'change_password', 'setup_mfa'],
        'Notifications'   => ['view_notifications', 'manage_notifications'],
        'Users'           => ['view_users', 'create_users', 'edit_users', 'delete_users'],
        'Roles'           => ['view_roles', 'create_roles', 'edit_roles', 'delete_roles'],
        'Sessions'        => ['view_sessions', 'revoke_sessions'],
    ],

];
