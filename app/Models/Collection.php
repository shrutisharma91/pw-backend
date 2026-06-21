<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $fillable = ['loan_application_id', 'dpd_bucket', 'overdue_amount', 'agent_id', 'status', 'npa_status'];

    public function loanApplication() { return $this->belongsTo(LoanApplication::class); }
    public function agent() { return $this->belongsTo(User::class, 'agent_id'); }
    public function bounces() { return $this->hasMany(BounceEvent::class); }

    //
}
