<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenureSlab extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tier_overrides' => 'array',
    ];

    public function emiType()
    {
        return $this->belongsTo(EmiType::class);
    }
}
