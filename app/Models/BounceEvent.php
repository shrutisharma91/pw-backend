<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BounceEvent extends Model
{
    protected $fillable = ['collection_id', 'amount', 'date', 'auto_retry_status'];

    public function collection() { return $this->belongsTo(Collection::class); }

    //
}
