<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $guarded = [];

    protected $casts = [
        'gst_verified_at'     => 'datetime',
        'agreement_esign_at'  => 'datetime',
        'disbursal_volume'    => 'decimal:2',
        'npa'                 => 'decimal:2',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function salesExec(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_exec_id');
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
