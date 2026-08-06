<?php

namespace App\Models;

use App\Modules\Lender\Models\Concerns\BelongsToLender;
use Illuminate\Database\Eloquent\Model;

class Disbursal extends Model
{
    use BelongsToLender;
    protected $fillable = ['loan_application_id', 'lender_id', 'amount', 'status', 'utr_number'];

    public function loanApplication() { return $this->belongsTo(LoanApplication::class); }
    public function lender() { return $this->belongsTo(Lender::class); }

    //
}
