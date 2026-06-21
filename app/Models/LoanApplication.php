<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    protected $fillable = ['customer_id', 'merchant_id', 'store_id', 'lender_id', 'amount', 'emi_type_id', 'status', 'sla_breached'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function merchant() { return $this->belongsTo(Merchant::class); }
    public function store() { return $this->belongsTo(Store::class); }
    public function lender() { return $this->belongsTo(Lender::class); }
    public function timelineEvents() { return $this->hasMany(LoanTimelineEvent::class); }
    public function documents() { return $this->hasMany(LoanDocument::class); }
    public function communications() { return $this->hasMany(LoanCommunication::class); }

    //
}
