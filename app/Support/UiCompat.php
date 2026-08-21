<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UiCompat
{
    public static function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    public static function normalizeRole(?string $role): ?string
    {
        if ($role === null || $role === '' || strtolower($role) === 'all') {
            return $role;
        }

        $map = [
            'super admin' => 'superadmin',
            'super_admin' => 'superadmin',
            'merchant admin' => 'merchant_admin',
            'store manager' => 'store_manager',
            'sales exec' => 'sales_exec',
            'sales executive' => 'sales_exec',
            'lender ops' => 'lender_ops',
            'lender operations' => 'lender_ops',
            'risk user' => 'risk_user',
        ];

        $key = strtolower(trim($role));

        return $map[$key] ?? Str::slug($role, '_');
    }

    public static function normalizeExpiry(mixed $policy): mixed
    {
        if ($policy === null || $policy === '') {
            return $policy;
        }

        $raw = (string) $policy;
        $map = [
            '30' => '30_days',
            '60' => '60_days',
            '90' => '90_days',
            '180' => '180_days',
        ];

        return $map[$raw] ?? $raw;
    }

    public static function normalizePeriod(?string $period, string $default = '30d'): string
    {
        $map = [
            'week' => '7d',
            '7d' => '7d',
            'month' => '30d',
            '30d' => '30d',
            'quarter' => '90d',
            '90d' => '90d',
            'year' => '1y',
            '1y' => '1y',
            'custom' => 'custom',
        ];

        $key = strtolower((string) $period);

        return $map[$key] ?? $default;
    }

    /** Inclusive calendar window so today's loans are not cut off at midnight. */
    public static function resolvePeriodRange(string $period, mixed $startDate = null, mixed $endDate = null): array
    {
        if ($period === 'custom') {
            return [
                Carbon::parse($startDate)->startOfDay()->toDateTimeString(),
                Carbon::parse($endDate)->endOfDay()->toDateTimeString(),
            ];
        }

        $end = now()->endOfDay();
        $start = match ($period) {
            '7d' => now()->subDays(7)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            '1y' => now()->subYear()->startOfDay(),
            default => now()->subDays(30)->startOfDay(),
        };

        return [$start->toDateTimeString(), $end->toDateTimeString()];
    }

    public static function slugRoleName(string $name): string
    {
        $slug = Str::slug($name, '_');
        if ($slug === '' || ! preg_match('/^[a-z]/', $slug)) {
            $slug = 'role_' . $slug;
        }

        return $slug;
    }

    public static function parameterCategory(string $group): string
    {
        return match ($group) {
            'security' => 'security',
            'rates', 'sla', 'limits' => 'loan',
            default => 'general',
        };
    }

    public static function documentType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return $type;
        }

        return match (strtolower(trim($type))) {
            'kyc' => 'kyc',
            'financial', 'invoice' => 'invoice',
            'income', 'statement' => 'statement',
            'legal', 'agreement' => 'agreement',
            default => strtolower(trim($type)),
        };
    }

    public static function ticketCategory(?string $category): ?string
    {
        if ($category === null || $category === '') {
            return $category;
        }

        $key = strtolower(trim($category));
        $map = [
            'general' => 'other',
            'other' => 'other',
            'dispute' => 'dispute',
            'complaint' => 'complaint',
            'technical' => 'technical',
            'billing' => 'billing',
            'kyc' => 'kyc',
            'loan' => 'loan',
            'settlement' => 'settlement',
            'agreement' => 'agreement',
        ];

        return $map[$key] ?? 'other';
    }

    public static function workflowType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return $type;
        }

        $map = [
            'override_approval' => 'manual_override',
            'manual_override' => 'manual_override',
        ];

        return $map[$type] ?? $type;
    }
}
