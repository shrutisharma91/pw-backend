<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementDispute extends Model
{
    protected $fillable = ['settlement_entry_id', 'reason', 'status'];

    public function entry() { return $this->belongsTo(SettlementEntry::class, 'settlement_entry_id'); }

    //
}
