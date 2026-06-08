<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementBatch extends Model
{
    protected $fillable = ['lender_id', 'date', 'total_gross', 'total_fees', 'total_net', 'utr_number', 'status'];

    public function lender() { return $this->belongsTo(Lender::class); }
    public function entries() { return $this->hasMany(SettlementEntry::class); }

    //
}
