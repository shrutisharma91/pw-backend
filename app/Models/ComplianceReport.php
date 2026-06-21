<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplianceReport extends Model
{
    protected $fillable = ['report_type', 'status', 'generated_by', 'file_url', 'parameters'];

    protected $casts = ['parameters' => 'array'];

    public function generator() { return $this->belongsTo(User::class, 'generated_by'); }

    //
}
