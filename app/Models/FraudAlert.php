<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FraudAlert extends Model
{
    protected $fillable = ['signal_type', 'severity', 'customer_id', 'merchant_id', 'status'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function merchant() { return $this->belongsTo(Merchant::class); }

    //
}
