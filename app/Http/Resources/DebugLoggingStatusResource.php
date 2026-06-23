<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebugLoggingStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled'    => (bool) ($this->resource['enabled'] ?? false),
            'updated_at' => $this->resource['updated_at'] ?? null,
            'updated_by' => $this->resource['updated_by'] ?? null,
        ];
    }
}
