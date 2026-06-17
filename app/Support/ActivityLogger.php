<?php

namespace App\Support;

use App\Models\AuditLog;

class ActivityLogger
{
    private array $properties = [];

    public function withProperties(array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    public function log(string $message): void
    {
        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'activity_log',
            'module'     => 'system',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'payload'    => array_merge($this->properties, ['message' => $message]),
            'created_at' => now(),
        ]);
    }
}
