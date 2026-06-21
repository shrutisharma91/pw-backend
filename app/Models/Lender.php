<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lender extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_url',
        'status',
        'api_status',
        'api_base_url',
        'api_credentials',
        'webhook_endpoints',
        'supported_tenures',
        'min_loan_amount',
        'max_loan_amount',
        'commission_structure',
    ];

    protected $casts = [
        'api_credentials' => 'encrypted:array',
        'webhook_endpoints' => 'array',
        'supported_tenures' => 'array',
        'commission_structure' => 'array',
        'min_loan_amount' => 'decimal:2',
        'max_loan_amount' => 'decimal:2',
    ];

    public function rules()
    {
        return $this->hasMany(LenderRule::class);
    }

    public function apiLogs()
    {
        return $this->hasMany(LenderApiLog::class);
    }
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
