<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantAgreement extends Model
{
    protected $fillable = [
        'merchant_id',
        'status',
        'document_url',
        'esign_provider',
        'version',
        'expires_at',
    ];
}
