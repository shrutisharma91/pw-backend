<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $notificationPreferences = $this->notification_channels ?? [
            'in_app'   => true,
            'email'    => true,
            'sms'      => false,
            'whatsapp' => false,
        ];

        return [
            'id'                       => $this->id,
            'name'                     => $this->name,
            'email'                    => $this->email,
            'mobile'                   => $this->mobile,
            'profile_photo'            => $this->profile_photo
                ? Storage::url($this->profile_photo)
                : null,
            'role'                     => $this->role,
            'roles'                    => $this->when(
                method_exists($this->resource, 'getRoleNames'),
                fn () => $this->getRoleNames()
            ),
            'mfa_enabled'              => (bool) $this->mfa_enabled,
            'mfa_channel'              => $this->mfa_channel,
            'theme'                    => $this->theme ?? 'light',
            'timezone'                 => $this->timezone ?? 'Asia/Kolkata',
            'notification_preferences'   => $notificationPreferences,
            'notification_channels'      => $notificationPreferences,
            'last_login_at'            => $this->last_login_at,
            'password_changed_at'      => $this->password_changed_at,
        ];
    }
}
