<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $guarded = [];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }
}
