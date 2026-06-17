<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'paid_at'              => 'datetime',
        'amount'               => 'decimal:2',
        'interest_component'   => 'decimal:2',
        'principal_component'  => 'decimal:2',
        'late_fee'             => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
