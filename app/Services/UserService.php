<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function createUser(array $data): array
    {
        $tempPassword = Str::random(12) . '@1';
        $attributes = $this->mapUserAttributes($data);

        $user = User::create(array_merge($attributes, [
            'password'    => Hash::make($tempPassword),
            'mfa_channel' => 'email',
            'is_active'   => true,
            'notification_channels' => [
                'in_app'   => true,
                'email'    => true,
                'sms'      => false,
                'whatsapp' => false,
            ],
        ]));

        $user->assignRole($data['role']);

        return [
            'user'         => $user->fresh(),
            'temp_password'=> $tempPassword,
        ];
    }

    public function updateUser(User $user, array $data): User
    {
        $attributes = $this->mapUserAttributes($data, $user);

        if (isset($data['role']) && $data['role'] !== $user->role) {
            $user->syncRoles([$data['role']]);
            $attributes['role'] = $data['role'];
        }

        if (array_key_exists('force_mfa', $data) && $data['force_mfa']) {
            $attributes['mfa_verified_at'] = null;
        } elseif (array_key_exists('mfa_enabled', $data) && $data['mfa_enabled'] && ! $user->mfa_enabled) {
            $attributes['mfa_verified_at'] = null;
        }

        if ($attributes !== []) {
            $user->update($attributes);
        }

        return $user->fresh();
    }

    private function mapUserAttributes(array $data, ?User $user = null): array
    {
        $attributes = [];

        foreach (['name', 'email', 'mobile', 'merchant_id', 'merchant_scope', 'password_expiry_policy'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('activation_date', $data)) {
            $attributes['activation_date'] = $data['activation_date'];
        }

        if (array_key_exists('deactivation_date', $data)) {
            $attributes['deactivation_date'] = $data['deactivation_date'];
        }

        $storeIds = $this->resolveStoreIds($data);

        if ($storeIds !== null) {
            $attributes['store_ids'] = $storeIds;
        }

        if (array_key_exists('force_mfa', $data)) {
            $attributes['mfa_enabled'] = (bool) $data['force_mfa'];
        } elseif (array_key_exists('mfa_enabled', $data)) {
            $attributes['mfa_enabled'] = (bool) $data['mfa_enabled'];
        }

        if ($user === null && ! array_key_exists('password_expiry_policy', $attributes)) {
            $attributes['password_expiry_policy'] = 'default';
        }

        return $attributes;
    }

    private function resolveStoreIds(array $data): ?array
    {
        if (array_key_exists('assigned_store_ids', $data)) {
            return array_values(array_unique(array_map('intval', $data['assigned_store_ids'] ?? [])));
        }

        if (array_key_exists('store_ids', $data)) {
            return array_values(array_unique(array_map('intval', $data['store_ids'] ?? [])));
        }

        return null;
    }
}
