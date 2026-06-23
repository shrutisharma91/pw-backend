<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class ProfileService
{
    private const DEFAULT_NOTIFICATION_PREFERENCES = [
        'in_app'   => true,
        'email'    => true,
        'sms'      => false,
        'whatsapp' => false,
    ];

    public function updateProfile(User $user, array $data, ?UploadedFile $profilePhoto = null): User
    {
        $updateData = [];

        foreach (['name', 'email', 'mobile', 'theme', 'timezone'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        $notificationInput = $data['notification_preferences'] ?? $data['notification_channels'] ?? null;

        if (is_array($notificationInput)) {
            $updateData['notification_channels'] = array_merge(
                $user->notification_channels ?? self::DEFAULT_NOTIFICATION_PREFERENCES,
                $notificationInput
            );
        }

        if ($profilePhoto) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $updateData['profile_photo'] = $profilePhoto->store('profile-photos', 'public');
        }

        if ($updateData !== []) {
            $user->update($updateData);
        }

        return $user->fresh();
    }

    public function changePassword(User $user, string $newPassword, ?string $currentTokenId = null): void
    {
        $hashedPassword = Hash::make($newPassword);

        $user->update([
            'password'            => $hashedPassword,
            'password_changed_at' => now(),
        ]);

        $user->passwordHistories()->create([
            'password'   => $hashedPassword,
            'created_at' => now(),
        ]);

        $sessionsQuery = $user->sessions()->where('is_active', true);

        if ($currentTokenId) {
            $sessionsQuery->where('token_id', '!=', $currentTokenId);
        }

        $sessionsQuery->update([
            'is_active'     => false,
            'logged_out_at' => now(),
        ]);
    }

    public function currentTokenId(): ?string
    {
        try {
            return JWTAuth::getPayload()->get('jti');
        } catch (\Throwable) {
            return null;
        }
    }
}
