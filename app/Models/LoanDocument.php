<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDocument extends Model
{
    protected $fillable = ['loan_application_id', 'document_type', 'file_path', 'status'];

    public function loanApplication() { return $this->belongsTo(LoanApplication::class); }

    //
}
