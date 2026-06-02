<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LenderWaterfall extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'priority_order',
        'category_id',
        'region',
        'time_window_start',
        'time_window_end',
        'status',
    ];

    protected $casts = [
        'priority_order' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
