<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmiType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'allowed_merchant_tiers' => 'array',
        'effective_from' => 'date',
    ];

    public function tenureSlabs()
    {
        return $this->hasMany(TenureSlab::class);
    }
}
