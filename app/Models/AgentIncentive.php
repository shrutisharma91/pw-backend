<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentIncentive extends Model
{
    protected $fillable = [
        'sales_exec_id',
        'loan_application_id',
        'merchant_id',
        'period',
        'loan_amount',
        'commission_rate_pct',
        'commission_amount',
        'payout_status',
        'tier',
        'paid_at',
    ];

    protected $casts = [
        'loan_amount'          => 'decimal:2',
        'commission_rate_pct'  => 'decimal:2',
        'commission_amount'    => 'decimal:2',
        'paid_at'              => 'datetime',
    ];

    public function salesExec(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_exec_id');
    }

    public function loanApplication(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
