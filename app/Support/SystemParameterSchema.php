<?php

namespace App\Support;

class SystemParameterSchema
{
    public const DEBUG_LOGGING_KEY = 'debug_logging_enabled';

    /**
     * @return array<string, array{type: string, label: string, group: string}>
     */
    public static function definitions(): array
    {
        return [
            'default_interest_rate'           => ['type' => 'float',    'label' => 'Default Interest Rate (%)',                'group' => 'rates'],
            'default_processing_fee'          => ['type' => 'float',    'label' => 'Default Processing Fee (%)',               'group' => 'rates'],
            'default_late_payment_fee'        => ['type' => 'float',    'label' => 'Default Late Payment Fee (₹)',             'group' => 'rates'],
            'default_bounce_charge'           => ['type' => 'float',    'label' => 'Bounce Charge (₹)',                        'group' => 'rates'],
            'max_merchant_discount'           => ['type' => 'float',    'label' => 'Max Merchant Discount (%)',                'group' => 'rates'],

            'otp_expiry_minutes'              => ['type' => 'int',      'label' => 'OTP Expiry (minutes)',                     'group' => 'security'],
            'otp_max_retries'                 => ['type' => 'int',      'label' => 'OTP Max Retries',                          'group' => 'security'],
            'login_lockout_attempts'            => ['type' => 'int',      'label' => 'Login Lockout Attempts',                   'group' => 'security'],
            'login_lockout_minutes'           => ['type' => 'int',      'label' => 'Login Lockout Duration (min)',             'group' => 'security'],
            'session_timeout_minutes'         => ['type' => 'int',      'label' => 'Session Timeout (minutes)',                'group' => 'security'],
            'password_expiry_days'            => ['type' => 'int',      'label' => 'Password Expiry (days)',                   'group' => 'security'],
            'mfa_trusted_device_days'         => ['type' => 'int',      'label' => 'MFA Trusted Device (days)',                'group' => 'security'],
            'reset_link_expiry_minutes'       => ['type' => 'int',      'label' => 'Password Reset Link Expiry (min)',         'group' => 'security'],

            'kyc_review_sla_hours'            => ['type' => 'int',      'label' => 'KYC Review SLA (hours)',                   'group' => 'sla'],
            'loan_approval_sla_minutes'       => ['type' => 'int',      'label' => 'Loan Approval SLA (minutes)',              'group' => 'sla'],
            'disbursal_sla_hours'             => ['type' => 'int',      'label' => 'Disbursal SLA (hours)',                    'group' => 'sla'],
            'ticket_first_response_sla_hours' => ['type' => 'int',      'label' => 'Ticket First Response SLA (hrs)',            'group' => 'sla'],
            'offer_approval_sla_hours'        => ['type' => 'int',      'label' => 'Offer Approval SLA (hours)',               'group' => 'sla'],

            'max_loan_amount'                 => ['type' => 'int',      'label' => 'Max Loan Amount (₹)',                      'group' => 'limits'],
            'min_loan_amount'                 => ['type' => 'int',      'label' => 'Min Loan Amount (₹)',                      'group' => 'limits'],
            'max_emi_tenure_months'           => ['type' => 'int',      'label' => 'Max EMI Tenure (months)',                  'group' => 'limits'],
            'manual_override_threshold'       => ['type' => 'int',      'label' => 'Manual Override Dual-Auth Threshold (₹)',   'group' => 'limits'],
            'auto_approval_offer_threshold'   => ['type' => 'int',      'label' => 'Auto-Approve Offer Threshold (₹)',         'group' => 'limits'],

            'maintenance_mode'                => ['type' => 'bool',     'label' => 'Maintenance Mode',                           'group' => 'platform'],
            'maintenance_banner'              => ['type' => 'string',   'label' => 'Maintenance Banner Message',               'group' => 'platform'],
            'maintenance_ends_at'             => ['type' => 'datetime', 'label' => 'Maintenance Ends At',                      'group' => 'platform'],
            'platform_name'                   => ['type' => 'string',   'label' => 'Platform Name',                            'group' => 'platform'],
            'support_email'                   => ['type' => 'string',   'label' => 'Support Email',                            'group' => 'platform'],
            'support_phone'                   => ['type' => 'string',   'label' => 'Support Phone',                            'group' => 'platform'],
            self::DEBUG_LOGGING_KEY           => ['type' => 'bool',     'label' => 'Debug Logging',                            'group' => 'platform'],
        ];
    }

    /**
     * Default values for configurable parameters (used by reset).
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'default_interest_rate'           => '14.5',
            'default_processing_fee'          => '2.0',
            'default_late_payment_fee'        => '500',
            'default_bounce_charge'           => '250',
            'max_merchant_discount'           => '5.0',

            'otp_expiry_minutes'              => '5',
            'otp_max_retries'                 => '3',
            'login_lockout_attempts'          => '5',
            'login_lockout_minutes'           => '30',
            'session_timeout_minutes'         => '60',
            'password_expiry_days'            => '90',
            'mfa_trusted_device_days'         => '30',
            'reset_link_expiry_minutes'       => '60',

            'kyc_review_sla_hours'            => '48',
            'loan_approval_sla_minutes'       => '120',
            'disbursal_sla_hours'             => '24',
            'ticket_first_response_sla_hours' => '24',
            'offer_approval_sla_hours'        => '48',

            'max_loan_amount'                 => '500000',
            'min_loan_amount'                 => '5000',
            'max_emi_tenure_months'           => '36',
            'manual_override_threshold'       => '100000',
            'auto_approval_offer_threshold'   => '50000',

            'maintenance_mode'                => '0',
            'maintenance_banner'              => '',
            'maintenance_ends_at'             => '',
            'platform_name'                   => 'FinZ LMS',
            'support_email'                   => 'support@finz.com',
            'support_phone'                   => '+91-0000000000',
            self::DEBUG_LOGGING_KEY           => '0',
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function castValue(string $raw, string $type): mixed
    {
        return match ($type) {
            'int'      => (int) $raw,
            'float'    => (float) $raw,
            'bool'     => (bool) (int) $raw,
            'datetime' => $raw !== '' ? $raw : null,
            default    => $raw,
        };
    }

    public static function sanitizeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'int'   => (string) (int) $value,
            'float' => (string) (float) $value,
            'bool'  => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
