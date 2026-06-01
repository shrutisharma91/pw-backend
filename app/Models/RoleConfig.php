<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleConfig extends Model
{
    protected $table = 'role_configs';

    protected $fillable = [
        'role_name',
        'allowlist',
        'denylist',
        'concurrent_session_limit',
    ];

    protected $casts = [
        'allowlist'                => 'array',
        'denylist'                 => 'array',
        'concurrent_session_limit' => 'integer',
    ];

    /**
     * Check if a given IP matches the rules for a role.
     */
    public static function checkIPRules(string $role, string $ip): bool
    {
        $config = self::where('role_name', $role)->first();
        if (!$config) {
            return true; // No config, default allow
        }

        $allowlist = $config->allowlist ?? [];
        $denylist = $config->denylist ?? [];

        // If denylist is not empty, check if IP matches any entry
        if (!empty($denylist)) {
            foreach ($denylist as $pattern) {
                if (self::ipMatches($ip, $pattern)) {
                    return false; // Matched denylist, reject
                }
            }
        }

        // If allowlist is not empty, IP MUST match at least one entry
        if (!empty($allowlist)) {
            $matched = false;
            foreach ($allowlist as $pattern) {
                if (self::ipMatches($ip, $pattern)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false; // Did not match any allowlist pattern, reject
            }
        }

        return true;
    }

    /**
     * Helper to match IP against a pattern (supports exact and CIDR)
     */
    private static function ipMatches(string $ip, string $pattern): bool
    {
        $ip = trim($ip);
        $pattern = trim($pattern);

        if ($ip === $pattern) {
            return true;
        }

        if (str_contains($pattern, '/')) {
            list($subnet, $bits) = explode('/', $pattern);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false) {
                return false;
            }
            // Avoid shifting by 32
            if ($bits == 0) {
                return true;
            }
            $mask = ~((1 << (32 - $bits)) - 1);
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        return false;
    }
}
