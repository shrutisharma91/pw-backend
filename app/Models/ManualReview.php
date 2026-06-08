<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualReview extends Model
{
    protected $fillable = ['loan_application_id', 'risk_score', 'status', 'assigned_to', 'sla_deadline', 'sla_breached'];

    public function loanApplication() { return $this->belongsTo(LoanApplication::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'assigned_to'); }

    //
}
