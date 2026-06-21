<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementEntry extends Model
{
    protected $fillable = ['settlement_batch_id', 'merchant_id', 'loan_application_id', 'gross', 'fees', 'net', 'status'];

    public function batch() { return $this->belongsTo(SettlementBatch::class, 'settlement_batch_id'); }
    public function merchant() { return $this->belongsTo(Merchant::class); }
    public function loanApplication() { return $this->belongsTo(LoanApplication::class); }
    public function disputes() { return $this->hasMany(SettlementDispute::class); }

    //
}
