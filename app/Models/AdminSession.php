<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
|--------------------------------------------------------------------------
| AdminSession Model
|--------------------------------------------------------------------------
| Tracks every active login session for Screen 13 (Session Management).
| Each time a user logs in, a row is created here.
| Super Admin can see all active sessions and force-logout any of them.
*/

class AdminSession extends Model
{
    protected $table = 'admin_sessions';

    protected $fillable = [
        'user_id',
        'token_id',          // JWT jti (unique ID per token)
        'ip_address',
        'device_info',       // browser/OS info from User-Agent
        'device_type',       // mobile, desktop, tablet
        'location',          // city/country from IP
        'is_active',
        'logged_in_at',
        'last_active_at',
        'logged_out_at',
        'is_suspicious',     // flagged if new device or unusual hour
        'suspicious_reason',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'is_suspicious'  => 'boolean',
        'logged_in_at'   => 'datetime',
        'last_active_at' => 'datetime',
        'logged_out_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
