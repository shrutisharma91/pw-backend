<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| AdminNotification Model
|--------------------------------------------------------------------------
| Powers Screen 05 — Notification Center.
| Stores every in-app notification for the Super Admin.
| Types: approval_request, alert, system, mention, info
*/

class AdminNotification extends Model
{
    protected $table = 'admin_notifications';

    protected $fillable = [
        'user_id',
        'type',           // approval, alert, system, mention, info
        'priority',       // critical, high, medium, info
        'title',
        'message',
        'action_url',     // where to go when clicked
        'action_label',   // button label, e.g. "Review Merchant"
        'data',           // JSON — any extra context data
        'is_read',
        'is_archived',
        'snoozed_until',
        'read_at',
    ];

    protected $casts = [
        'is_read'      => 'boolean',
        'is_archived'  => 'boolean',
        'snoozed_until' => 'datetime',
        'read_at'      => 'datetime',
        'data'         => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope: only unarchived, not snoozed
    public function scopeVisible($query)
    {
        return $query->where('is_archived', false)
                     ->where(function ($q) {
                         $q->whereNull('snoozed_until')
                           ->orWhere('snoozed_until', '<', now());
                     });
    }
}
