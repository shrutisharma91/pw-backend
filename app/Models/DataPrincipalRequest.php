<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataPrincipalRequest extends Model
{
    protected $fillable = ['customer_id', 'request_type', 'status', 'resolution_notes'];

    public function customer() { return $this->belongsTo(Customer::class); }

    //
}
