<?php

namespace App\Support;

class UserAccessRules
{
    public const MERCHANT_SCOPES = ['platform', 'merchant', 'store'];

    public const PASSWORD_EXPIRY_POLICIES = [
        'default',
        'never',
        '30_days',
        '60_days',
        '90_days',
        '180_days',
    ];

    public const ROLES = [
        'merchant_admin',
        'store_manager',
        'sales_exec',
        'lender_ops',
        'risk_user',
        'customer',
    ];
}
