<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'name',
        'template_key',
        'channel',
        'subject',
        'variables',
        'sender_id',
        'dlt_template_id',
        'language',
        'status',
        'current_version',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'variables'   => 'array',
        'approved_at' => 'datetime',
    ];

    protected $appends = ['body'];

    public function getBodyAttribute(): ?string
    {
        if (array_key_exists('body', $this->attributes) && $this->attributes['body']) {
            return $this->attributes['body'];
        }

        return $this->relationLoaded('currentVersion')
            ? $this->currentVersion?->body
            : $this->currentVersion()?->first()?->body;
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NotificationTemplateVersion::class, 'template_id');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(NotificationTemplateVersion::class, 'template_id')
            ->where('is_active', true);
    }

    public function activeVersion(): HasOne
    {
        return $this->currentVersion();
    }
}