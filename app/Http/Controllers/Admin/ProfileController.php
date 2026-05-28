<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| ProfileController
|--------------------------------------------------------------------------
| Handles Screen 04 — Profile & Personal Settings
|
| APIs:
|   GET  /api/v1/admin/profile              → get my profile
|   PUT  /api/v1/admin/profile              → update name/mobile/photo
|   PUT  /api/v1/admin/profile/change-password
|   PUT  /api/v1/admin/profile/mfa-setup
|   PUT  /api/v1/admin/profile/preferences  → theme, timezone, notif channels
*/

class ProfileController extends Controller
{
    // ------------------------------------------------------------------
    // GET /api/v1/admin/profile
    // ------------------------------------------------------------------
    public function show()
    {
        /** @var User|null $user */
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'id'                   => $user->id,
                'name'                 => $user->name,
                'email'                => $user->email,
                'mobile'               => $user->mobile,
                'profile_photo'        => $user->profile_photo
                    ? Storage::url($user->profile_photo)
                    : null,
                'role'                 => $user->role,
                'roles'                => $user->getRoleNames(), // Spatie roles
                'mfa_enabled'          => $user->mfa_enabled,
                'mfa_channel'          => $user->mfa_channel,
                'theme'                => $user->theme ?? 'light',
                'timezone'             => $user->timezone ?? 'Asia/Kolkata',
                'notification_channels' => $user->notification_channels ?? [
                    'in_app'    => true,
                    'email'     => true,
                    'sms'       => false,
                    'whatsapp'  => false,
                ],
                'last_login_at'        => $user->last_login_at,
                'password_changed_at'  => $user->password_changed_at,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/profile
    // Update name, mobile, profile photo
    // ------------------------------------------------------------------
    public function update(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        $request->validate([
            'name'          => 'sometimes|string|max:100',
            'mobile'        => 'sometimes|string|size:10|unique:users,mobile,' . $user->id,
            'profile_photo' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048', // max 2MB
        ]);

        $updateData = [];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }

        if ($request->has('mobile')) {
            $updateData['mobile'] = $request->mobile;
        }

        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo
            if ($user->profile_photo) {
                Storage::delete($user->profile_photo);
            }
            // Store new photo in storage/app/public/profile-photos/
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $updateData['profile_photo'] = $path;
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'name'          => $user->fresh()->name,
                'mobile'        => $user->fresh()->mobile,
                'profile_photo' => $user->fresh()->profile_photo
                    ? Storage::url($user->fresh()->profile_photo)
                    : null,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/profile/change-password
    // ------------------------------------------------------------------
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&]/',
                'different:current_password', // can't be same as current
            ],
        ], [
            'new_password.regex' => 'Password must include uppercase, number, and special character.',
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        // Check password history (per spec: can't reuse last 5 passwords)
        $pastPasswords = $user->passwordHistories()->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($pastPasswords as $pastPassword) {
            if (Hash::check($request->new_password, $pastPassword->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot reuse any of your last 5 passwords.',
                    'code'    => 'password_reuse_blocked',
                ], 422);
            }
        }

        // Update password
        $user->update([
            'password'            => Hash::make($request->new_password),
            'password_changed_at' => now(),
        ]);

        // Save password history
        $user->passwordHistories()->create([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/profile/mfa-setup
    // Enable, disable, or change MFA channel
    // ------------------------------------------------------------------
    public function mfaSetup(Request $request)
    {
        $request->validate([
            'action'      => 'required|in:enable,disable,change_channel',
            'channel'     => 'required_if:action,enable,change_channel|in:email,totp',
            'otp_confirm' => 'sometimes|string|size:6', // Confirm current OTP before disabling MFA
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        if ($request->action === 'disable') {
            // Require OTP verification before disabling MFA (security)
            if (!$request->otp_confirm) {
                // Send OTP first
                app(\App\Services\MFAService::class)->sendOTP($user);
                return response()->json([
                    'success'       => true,
                    'action_needed' => 'verify_otp',
                    'message'       => 'OTP sent. Provide it to confirm disabling MFA.',
                ]);
            }

            // Verify the OTP
            $result = app(\App\Services\MFAService::class)->verifyOTP($user, $request->otp_confirm);
            if (!$result['success']) {
                return response()->json(['success' => false, 'message' => $result['message']], 422);
            }

            $user->update(['mfa_enabled' => false, 'mfa_channel' => null]);
            return response()->json(['success' => true, 'message' => 'MFA disabled.']);
        }

        if ($request->action === 'enable' || $request->action === 'change_channel') {
            $user->update([
                'mfa_enabled' => true,
                'mfa_channel' => $request->channel,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'MFA ' . ($request->action === 'enable' ? 'enabled' : 'updated') . ' successfully.',
                'channel' => $request->channel,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/profile/preferences
    // Theme toggle (light/dark), timezone, notification channels
    // ------------------------------------------------------------------
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'theme'    => 'sometimes|in:light,dark',
            'timezone' => 'sometimes|string|timezone',
            'notification_channels' => 'sometimes|array',
            'notification_channels.in_app'   => 'sometimes|boolean',
            'notification_channels.email'    => 'sometimes|boolean',
            'notification_channels.sms'      => 'sometimes|boolean',
            'notification_channels.whatsapp' => 'sometimes|boolean',
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        $updateData = [];

        if ($request->has('theme')) {
            $updateData['theme'] = $request->theme;
        }

        if ($request->has('timezone')) {
            $updateData['timezone'] = $request->timezone;
        }

        if ($request->has('notification_channels')) {
            // Merge with existing — don't overwrite all channels if only one sent
            $existing = $user->notification_channels ?? [];
            $updateData['notification_channels'] = array_merge($existing, $request->notification_channels);
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Preferences saved.',
            'data' => [
                'theme'                 => $user->fresh()->theme,
                'timezone'              => $user->fresh()->timezone,
                'notification_channels' => $user->fresh()->notification_channels,
            ],
        ]);
    }
}
