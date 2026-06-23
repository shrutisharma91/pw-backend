<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'title'             => $this->title,
            'description'       => $this->description,
            'document_type'     => $this->document_type,
            'category'          => $this->document_type,
            'tags'              => $this->tags ?? [],
            'entity_type'       => $this->entity_type,
            'entity_id'         => $this->entity_id,
            'original_filename' => $this->original_filename,
            'file_size_bytes'   => $this->file_size_bytes,
            'mime_type'         => $this->mime_type,
            'status'            => $this->status,
            'ocr_status'        => $this->ocr_status,
            'virus_scan_status' => $this->virus_scan_status,
            'version'           => $this->version,
            'uploaded_by'       => $this->uploaded_by,
            'uploaded_at'       => $this->created_at,
            'created_at'        => $this->created_at,
            'retention_until'   => $this->retention_until,
        ];
    }
}
