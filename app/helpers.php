<?php

use App\Models\AuditLog;
use App\Support\ActivityLogger;

if (! function_exists('activity')) {
    function activity(): ActivityLogger
    {
        return new ActivityLogger;
    }
}

if (! function_exists('flush_cache_tags')) {
    function flush_cache_tags(array $tags): void
    {
        $store = \Illuminate\Support\Facades\Cache::getStore();

        if (! method_exists($store, 'tags')) {
            return;
        }

        \Illuminate\Support\Facades\Cache::tags($tags)->flush();
    }
}

if (! function_exists('audit_log')) {
    function audit_log(string $action, string $module, array $payload = []): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'module'     => $module,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'payload'    => $payload,
            'created_at' => now(),
        ]);
    }
}
