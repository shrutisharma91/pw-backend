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