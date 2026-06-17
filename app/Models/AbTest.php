<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbTest extends Model
{
    protected $table = 'ab_tests';

    protected $fillable = [
        'flag_id',
        'name',
        'variant_a_value',
        'variant_b_value',
        'traffic_split',
        'metric',
        'status',
        'start_at',
        'end_at',
        'ended_at',
        'created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function flag(): BelongsTo
    {
        return $this->belongsTo(FeatureFlag::class, 'flag_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AbTestEvent::class, 'test_id');
    }
}
