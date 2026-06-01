<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                'mfa_enabled'          => true,
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
}
