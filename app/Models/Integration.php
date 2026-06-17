<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    protected $fillable = [
        'name',
        'category',
        'provider_key',
        'base_url',
        'api_key_enc',
        'api_secret_enc',
        'webhook_url',
        'is_active',
        'is_primary',
        'is_fallback',
        'priority',
        'timeout_seconds',
        'retry_attempts',
        'credential_rotation_due_at',
        'notes',
    ];

    /**
     * Never expose encrypted credentials in JSON responses
     */
    protected $hidden = [
        'api_key_enc',
        'api_secret_enc',
    ];

    protected $casts = [
        'is_active'                  => 'boolean',
        'is_primary'                 => 'boolean',
        'is_fallback'                => 'boolean',
        'credential_rotation_due_at' => 'datetime',
    ];

    public function callLogs(): HasMany
    {
        return $this->hasMany(IntegrationCallLog::class);
    }

    /**
     * Scope: only active integrations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: primary provider for a given category
     */
    public function scopePrimaryFor($query, string $category)
    {
        return $query->where('category', $category)->where('is_primary', true)->where('is_active', true);
    }
}