<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

/*
|--------------------------------------------------------------------------
| User Model — FinZ Super Admin
|--------------------------------------------------------------------------
|
| This replaces the default Laravel User model.
| It supports:
|   - JWT Authentication (JWTSubject interface)
|   - Spatie RBAC (HasRoles trait)
|   - MFA fields (mfa_enabled, mfa_secret, mfa_verified_at)
|   - Session tracking
|   - Brute-force lockout
|
*/

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles;

    protected $table = 'users';

    protected $guard_name = 'api';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'profile_photo',
        'role',                  // superadmin, merchant_admin, store_manager, etc.
        'mfa_enabled',           // boolean — is MFA turned on?
        'mfa_secret',            // TOTP secret key (encrypted)
        'mfa_channel',           // 'totp' or 'email'
        'mfa_verified_at',       // when MFA was last verified in this session
        'failed_login_attempts', // count of failed logins
        'locked_until',          // locked out until this datetime
        'last_login_at',
        'last_login_ip',
        'timezone',
        'theme',                 // 'light' or 'dark'
        'notification_channels', // JSON: {in_app: true, email: true, sms: false, whatsapp: false}
        'is_active',
        'password_changed_at',
        'merchant_id',           // null for super admin
        'merchant_scope',        // platform, merchant, store
        'store_ids',             // JSON array for store managers
        'password_expiry_policy',
        'activation_date',
        'deactivation_date',
        'mfa_recovery_codes',
    ];

    protected $hidden = [
        'password',
        'mfa_secret',
        'mfa_recovery_codes',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at'      => 'datetime',
        'mfa_verified_at'        => 'datetime',
        'locked_until'           => 'datetime',
        'last_login_at'          => 'datetime',
        'password_changed_at'    => 'datetime',
        'activation_date'        => 'date',
        'deactivation_date'      => 'date',
        'mfa_enabled'            => 'boolean',
        'is_active'              => 'boolean',
        'notification_channels'  => 'array',
        'store_ids'              => 'array',
        'mfa_recovery_codes'     => 'array',
        'password'               => 'hashed',
    ];

    // -------------------------------------------------------
    // JWT required methods
    // -------------------------------------------------------

    // This tells JWT what to use as the unique identifier in the token
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Extra data to include inside the JWT token payload
    public function getJWTCustomClaims()
    {
        return [
            'role'        => $this->role,
            'name'        => $this->name,
            'mfa_enabled' => $this->mfa_enabled,
        ];
    }

    // -------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------

    // Check if account is currently locked due to too many failed logins
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    // Check if the user has verified MFA in this session
    public function hasMFAVerifiedThisSession(): bool
    {
        if (!$this->mfa_verified_at) {
            return false;
        }
        // MFA verification valid for 8 hours
        return $this->mfa_verified_at->diffInHours(now()) < 8;
    }

    // Increment failed login counter. Lock if >= 5 attempts
    public function incrementFailedLogin(): void
    {
        $this->increment('failed_login_attempts');
        if ($this->failed_login_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    // Reset failed login counter on successful login
    public function resetFailedLogin(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => now(),
            'last_login_ip'         => request()->ip(),
        ]);
    }

    // Relationships
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function sessions()
    {
        return $this->hasMany(AdminSession::class);
    }

    public function notifications()
    {
        return $this->hasMany(AdminNotification::class);
    }

    public function passwordHistories()
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
