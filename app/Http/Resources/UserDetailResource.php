<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'name'                   => $this->name,
            'email'                  => $this->email,
            'mobile'                 => $this->mobile,
            'role'                   => $this->role,
            'roles'                  => $this->getRoleNames(),
            'permissions'            => $this->getAllPermissions()->pluck('name'),
            'merchant_id'            => $this->merchant_id,
            'merchant_scope'         => $this->merchant_scope,
            'store_ids'              => $this->store_ids,
            'assigned_store_ids'     => $this->store_ids ?? [],
            'mfa_enabled'            => $this->mfa_enabled,
            'force_mfa'              => (bool) $this->mfa_enabled,
            'mfa_channel'            => $this->mfa_channel,
            'password_expiry_policy' => $this->password_expiry_policy ?? 'default',
            'activation_date'        => $this->activation_date?->toDateString(),
            'deactivation_date'      => $this->deactivation_date?->toDateString(),
            'is_active'              => $this->is_active,
            'last_login_at'          => $this->last_login_at,
            'last_login_ip'          => $this->last_login_ip,
            'created_at'             => $this->created_at,
        ];
    }
}
