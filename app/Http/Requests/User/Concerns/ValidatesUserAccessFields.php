<?php

namespace App\Http\Requests\User\Concerns;

use App\Models\Store;
use App\Support\UserAccessRules;
use Illuminate\Validation\Validator;

trait ValidatesUserAccessFields
{
    protected function userAccessFieldRules(?int $userId = null): array
    {
        $mobileRule = 'sometimes|string|size:10|regex:/^[0-9]{10}$/';

        if ($userId) {
            $mobileRule .= '|unique:users,mobile,' . $userId;
        } else {
            $mobileRule = 'required|string|size:10|regex:/^[0-9]{10}$/|unique:users,mobile';
        }

        return [
            'name'                      => ($userId ? 'sometimes' : 'required') . '|string|max:100',
            'email'                     => ($userId ? 'sometimes' : 'required') . '|email|max:255|unique:users,email' . ($userId ? ',' . $userId : ''),
            'mobile'                    => $mobileRule,
            'role'                      => ($userId ? 'sometimes' : 'required') . '|in:' . implode(',', UserAccessRules::ROLES),
            'merchant_id'               => 'nullable|integer|exists:merchants,id',
            'merchant_scope'            => 'nullable|in:' . implode(',', UserAccessRules::MERCHANT_SCOPES),
            'assigned_store_ids'        => 'nullable|array',
            'assigned_store_ids.*'      => 'integer|exists:stores,id',
            'store_ids'                 => 'nullable|array',
            'store_ids.*'               => 'integer|exists:stores,id',
            'force_mfa'                 => 'sometimes|boolean',
            'mfa_enabled'               => 'sometimes|boolean',
            'password_expiry_policy'    => 'nullable|in:' . implode(',', UserAccessRules::PASSWORD_EXPIRY_POLICIES),
            'activation_date'           => 'nullable|date',
            'deactivation_date'         => 'nullable|date|after_or_equal:activation_date',
        ];
    }

    protected function validateUserAccessFields(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $merchantScope = $this->input('merchant_scope');
            $merchantId = $this->input('merchant_id');
            $storeIds = $this->input('assigned_store_ids', $this->input('store_ids', []));

            if ($merchantScope === 'merchant' && empty($merchantId)) {
                $validator->errors()->add('merchant_id', 'merchant_id is required when merchant_scope is merchant.');
            }

            if ($merchantScope === 'store' && empty($storeIds)) {
                $validator->errors()->add('assigned_store_ids', 'At least one store is required when merchant_scope is store.');
            }

            if (! empty($storeIds) && ! empty($merchantId)) {
                $invalidCount = Store::query()
                    ->whereIn('id', $storeIds)
                    ->where('merchant_id', '!=', $merchantId)
                    ->count();

                if ($invalidCount > 0) {
                    $validator->errors()->add('assigned_store_ids', 'All assigned stores must belong to the selected merchant.');
                }
            }

            if ($this->filled('activation_date') && $this->filled('deactivation_date')) {
                if ($this->date('deactivation_date')->lt($this->date('activation_date'))) {
                    $validator->errors()->add('deactivation_date', 'Deactivation date cannot be earlier than activation date.');
                }
            }
        });
    }
}
