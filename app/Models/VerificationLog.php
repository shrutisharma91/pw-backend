<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationLog extends Model
{
    protected $fillable = [
        'merchant_id',
        'api_type',
        'status',
        'provider',
        'request_payload',
        'response_payload',
        'cost',
        'error_message'
    ];
}
