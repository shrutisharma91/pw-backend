<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'mapped_category_id'
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function mappedCategory()
    {
        return $this->belongsTo(Category::class, 'mapped_category_id');
    }
}
