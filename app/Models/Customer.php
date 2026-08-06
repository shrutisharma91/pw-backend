<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Phase 2 — Customer Financing Portal identity (mobile OTP + JWT).
 * Shared with Super Admin loan views via customer_id on loans.
 */
class Customer extends Authenticatable implements JWTSubject
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'pan_number',
        'whatsapp_opt_in',
        'dob',
        'monthly_income',
        'aadhaar_last4',
        'last_login_at',
        'is_active',
    ];

    protected $casts = [
        'whatsapp_opt_in' => 'boolean',
        'is_active'       => 'boolean',
        'dob'             => 'date',
        'monthly_income'  => 'decimal:2',
        'last_login_at'   => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'guard' => 'customer',
            'phone' => $this->phone,
            'name'  => $this->name,
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
