<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanCommunication extends Model
{
    protected $fillable = ['loan_application_id', 'type', 'content', 'status'];

    public function loanApplication() { return $this->belongsTo(LoanApplication::class); }

    //
}
