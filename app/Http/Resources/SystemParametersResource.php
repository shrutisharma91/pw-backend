<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemParametersResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $grouped
     */
    public function __construct(array $grouped)
    {
        parent::__construct($grouped);
    }

    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
