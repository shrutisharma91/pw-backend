<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTarget extends Model
{
    protected $fillable = [
        'sales_exec_id',
        'period',
        'merchants_onboard_target',
        'disbursal_volume_target',
    ];

    protected $casts = [
        'merchants_onboard_target' => 'integer',
        'disbursal_volume_target'  => 'decimal:2',
    ];

    public function salesExec(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_exec_id');
    }
}
