<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationCallLog extends Model
{
    protected $fillable = [
        'integration_id',
        'endpoint',
        'http_status',
        'response_time_ms',
        'is_success',
        'error_code',
        'cost',
        'entity_type',
        'entity_id',
    ];

    protected $casts = [
        'is_success' => 'boolean',
        'cost'       => 'decimal:4',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
