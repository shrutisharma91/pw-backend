<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlacklistEntry extends Model
{
    protected $fillable = ['category', 'value', 'reason', 'source', 'expiry_date', 'severity', 'status', 'override_approved_by'];

    public function overrideApprover() { return $this->belongsTo(User::class, 'override_approved_by'); }

    //
}
