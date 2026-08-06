<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date'  => 'date',
        'paid_at'   => 'datetime',
        'principal' => 'decimal:2',
        'interest'  => 'decimal:2',
        'total_emi' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
