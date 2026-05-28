<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LenderRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'conditions',
        'lender_id',
        'status',
        'version',
        'ab_test_split',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
    ];

    public function lender()
    {
        return $this->belongsTo(Lender::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
