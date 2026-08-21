<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workflow extends Model
{
    protected $fillable = [
        'name',
        'workflow_type',
        'description',
        'status',
        'current_version',
        'created_by',
        'published_by',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected $appends = ['canvas', 'steps'];

    public function getCanvasAttribute(): ?array
    {
        return $this->activeVersion?->canvas;
    }

    public function getStepsAttribute(): int
    {
        return count($this->activeVersion?->canvas['nodes'] ?? []);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(WorkflowVersion::class)->where('is_active', true);
    }
}