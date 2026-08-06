<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'disbursed_at'             => 'datetime',
        'approved_at'              => 'datetime',
        'closed_at'                => 'datetime',
        'next_due_date'            => 'date',
        'is_npa'                   => 'boolean',
        'loan_amount'              => 'decimal:2',
        'outstanding_amount'       => 'decimal:2',
        'processing_fee_collected' => 'decimal:2',
        'emi_amount'               => 'decimal:2',
        'down_payment'             => 'decimal:2',
        'interest_rate'            => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('installment_no');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
