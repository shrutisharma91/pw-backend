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

    protected $appends = ['file_name', 'name', 'type', 'category', 'size_kb', 'size'];

    public function getFileNameAttribute(): ?string
    {
        return $this->original_filename ?? $this->title;
    }

    public function getNameAttribute(): ?string
    {
        return $this->title ?? $this->original_filename;
    }

    public function getTypeAttribute(): ?string
    {
        return $this->document_type;
    }

    public function getCategoryAttribute(): ?string
    {
        return $this->document_type;
    }

    public function getSizeKbAttribute(): float
    {
        return round(((int) $this->file_size_bytes) / 1024, 1);
    }

    public function getSizeAttribute(): string
    {
        $bytes = (int) $this->file_size_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1024, 1) . ' KB';
    }

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