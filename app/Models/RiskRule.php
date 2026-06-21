<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskRule extends Model
{
    protected $fillable = ['rule_type', 'name', 'parameters', 'threshold', 'action', 'status'];

    protected $casts = ['parameters' => 'array'];

    //
}
