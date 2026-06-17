<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeatureFlag extends Model
{
    protected $fillable = [
        'name',
        'key',
        'description',
        'type',
        'default_value',
        'rollout_status',
        'rollout_percent',
        'cohort_rules',
        'created_by',
        'updated_by',
        'killed_at',
        'killed_by',
        'kill_reason',
    ];

    protected $casts = [
        'rollout_percent' => 'integer',
        'cohort_rules'    => 'array',
        'killed_at'       => 'datetime',
    ];

    public function abTests(): HasMany
    {
        return $this->hasMany(AbTest::class, 'flag_id');
    }

    public function activeAbTest(): HasOne
    {
        return $this->hasOne(AbTest::class, 'flag_id')->where('status', 'active');
    }

    /**
     * Check if this flag is enabled for a given merchant
     * Used by other parts of the app to gate features
     */
    public function isEnabledFor(int $merchantId): bool
    {
        if ($this->rollout_status === 'off' || $this->killed_at) {
            return false;
        }

        if ($this->rollout_status === 'on') {
            return true;
        }

        // Partial rollout — check cohort rules first, then percentage
        if ($this->cohort_rules) {
            $rules   = $this->cohort_rules;
            $merchant = \Illuminate\Support\Facades\DB::table('merchants')->find($merchantId);

            if (!$merchant) return false;

            if (isset($rules['merchant_tier']) && $merchant->tier !== $rules['merchant_tier']) return false;
            if (isset($rules['region'])         && $merchant->state !== $rules['region'])         return false;
            if (isset($rules['signup_after'])   && $merchant->created_at < $rules['signup_after']) return false;
        }

        // Percentage rollout — deterministic based on merchant ID
        return ($merchantId % 100) < $this->rollout_percent;
    }
}