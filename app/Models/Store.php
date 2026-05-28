<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_active_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'declared_latitude' => 'decimal:8',
        'declared_longitude' => 'decimal:8',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_store')
                    ->withPivot('stock_quantity')
                    ->withTimestamps();
    }
}
