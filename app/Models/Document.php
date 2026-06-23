<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'title',
        'description',
        'tags',
        'document_type',
        'entity_type',
        'entity_id',
        'original_filename',
        'storage_path',
        'file_size_bytes',
        'mime_type',
        'status',
        'ocr_status',
        'ocr_text',
        'virus_scan_status',
        'version',
        'uploaded_by',
        'deleted_at',
        'deleted_by',
        'retention_until',
    ];

    protected $casts = [
        'tags'            => 'array',
        'retention_until' => 'datetime',
        'deleted_at'      => 'datetime',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /**
     * Scope to exclude soft-deleted documents
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }
}