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
        'Analytics'       => [
            'analytics.business.view',
            'analytics.lender.view',
            'analytics.sales.view',
            'analytics.reports.view',
        ],
        'NotificationOps' => [
            'notifications.templates.view',
            'notifications.templates.create',
            'notifications.templates.edit',
            'notifications.templates.approve',
            'notifications.logs.view',
            'notifications.logs.resend',
        ],
        'Documents'       => [
            'documents.view',
            'documents.upload',
            'documents.share',
            'documents.ocr',
            'documents.delete',
            'documents.retention',
        ],
        'System'          => [
            'system.workflows.view',
            'system.workflows.create',
            'system.workflows.edit',
            'system.workflows.publish',
            'system.integrations.view',
            'system.integrations.edit',
            'system.integrations.toggle',
            'system.flags.view',
            'system.flags.create',
            'system.flags.edit',
            'system.flags.kill',
            'system.flags.abtest',
            'system.parameters.view',
            'system.parameters.edit',
            'system.maintenance.toggle',
        ],
        'Support'         => [
            'support.tickets.view',
            'support.tickets.create',
            'support.tickets.edit',
            'support.tickets.respond',
            'support.tickets.escalate',
            'support.tickets.reassign',
            'support.tickets.bulk',
        ],
    ],

];
