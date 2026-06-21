<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedFilter extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'filter_payload'];

    protected $casts = [
        'filter_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
