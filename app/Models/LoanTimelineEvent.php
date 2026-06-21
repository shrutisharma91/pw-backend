<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanTimelineEvent extends Model
{
    protected $fillable = ['loan_application_id', 'stage', 'event_name', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function loanApplication() { return $this->belongsTo(LoanApplication::class); }

    //
}
