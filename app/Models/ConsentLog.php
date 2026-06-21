<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentLog extends Model
{
    protected $fillable = ['customer_id', 'merchant_id', 'consent_type', 'version', 'payload', 'ip_address', 'device', 'status'];

    protected $casts = ['payload' => 'array'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function merchant() { return $this->belongsTo(Merchant::class); }

    //
}
