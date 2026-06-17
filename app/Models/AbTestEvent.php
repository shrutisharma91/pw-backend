<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbTestEvent extends Model
{
    public $timestamps = false;

    protected $table = 'ab_test_events';

    protected $fillable = [
        'test_id',
        'variant',
        'entity_id',
        'converted',
        'event_at',
    ];

    protected $casts = [
        'converted' => 'boolean',
        'event_at'  => 'datetime',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(AbTest::class, 'test_id');
    }
}
