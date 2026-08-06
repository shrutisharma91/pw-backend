<?php

namespace App\Models;

use App\Modules\Lender\Models\Concerns\BelongsToLender;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    use BelongsToLender;
    protected $fillable = [
        'customer_id',
        'merchant_id',
        'store_id',
        'lender_id',
        'amount',
        'emi_type_id',
        'product_id',
        'tenure_months',
        'down_payment',
        'emi_amount',
        'status',
        'sla_breached',
        'application_payload',
    ];

    protected $casts = [
        'amount'               => 'decimal:2',
        'down_payment'         => 'decimal:2',
        'emi_amount'           => 'decimal:2',
        'sla_breached'         => 'boolean',
        'application_payload'  => 'array',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function merchant() { return $this->belongsTo(Merchant::class); }
    public function store() { return $this->belongsTo(Store::class); }
    public function lender() { return $this->belongsTo(Lender::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function timelineEvents() { return $this->hasMany(LoanTimelineEvent::class); }
    public function documents() { return $this->hasMany(LoanDocument::class); }
    public function communications() { return $this->hasMany(LoanCommunication::class); }
}
