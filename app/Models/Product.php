<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'category_id',
        'brand_id',
        'name',
        'sku',
        'price',
        'status',
        'financing_eligibility',
        'flagged_for_review',
        'delist_reason',
    ];

    protected $casts = [
        'financing_eligibility' => 'boolean',
        'flagged_for_review' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'product_store')
                    ->withPivot('stock_quantity')
                    ->withTimestamps();
    }
}
