<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LenderApiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'lender_id',
        'endpoint',
        'request_payload',
        'response_payload',
        'status_code',
        'latency_ms',
        'is_timeout',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'is_timeout' => 'boolean',
    ];

    public function lender()
    {
        return $this->belongsTo(Lender::class);
    }
}
