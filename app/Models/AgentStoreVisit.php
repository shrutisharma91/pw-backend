<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStoreVisit extends Model
{
    protected $fillable = [
        'sales_exec_id',
        'merchant_id',
        'store_id',
        'latitude',
        'longitude',
        'checked_in_at',
        'qr_standee_placed',
        'staff_trained',
        'pos_active',
        'merchant_active',
        'notes',
        'ticket_id',
    ];

    protected $casts = [
        'latitude'           => 'decimal:8',
        'longitude'          => 'decimal:8',
        'checked_in_at'      => 'datetime',
        'qr_standee_placed'  => 'boolean',
        'staff_trained'      => 'boolean',
        'pos_active'         => 'boolean',
        'merchant_active'    => 'boolean',
    ];

    public function salesExec(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_exec_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
