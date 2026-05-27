<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MFAService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| MFAController
|--------------------------------------------------------------------------
| Handles Screen 02 — MFA / 2FA Verification
|
| APIs:
|   POST /api/v1/auth/mfa/verify  → verify the 6-digit OTP
|   POST /api/v1/auth/mfa/resend  → resend OTP (if user didn't receive it)
|
| Flow:
|   After login, if mfa_required=true, frontend shows Screen 02.
|   User enters the 6-digit code from Email or Google Authenticator.
|   On success, we stamp mfa_verified_at on the user so the
|   MFAVerified middleware lets them through.
*/

class MFAController extends Controller
{
    public function __construct(private MFAService $mfaService) {}

    // ------------------------------------------------------------------
    // POST /api/v1/auth/mfa/verify
    // ------------------------------------------------------------------
    public function verify(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        /** @var User|null $user */
        $user   = Auth::user();
        $result = $this->mfaService->verifyOTP($user, $request->otp);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'code'    => $result['code'] ?? null,
            ], 401);
        }

        // Stamp MFA verified time on user record
        $user->update(['mfa_verified_at' => now()]);

        // Return the same token + user info (now MFA-verified)
        return response()->json([
            'success'    => true,
            'message'    => 'MFA verified. Welcome to FinZ Admin.',
            'token'      => JWTAuth::getToken()->get(),
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'role'     => $user->role,
                'theme'    => $user->theme,
                'timezone' => $user->timezone,
                'photo'    => $user->profile_photo,
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/mfa/resend
    // ------------------------------------------------------------------
    public function resend(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        // Don't resend if one was already sent < 60 seconds ago
        if ($this->mfaService->hasActivOTP($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting a new OTP.',
            ], 429);
        }

        $sent = $this->mfaService->sendOTP($user);

        if (!$sent) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
            ], 500);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'OTP sent successfully.',
            'expires_in' => $this->mfaService->getOTPTTL(), // 300 seconds
            'channel'    => $user->mfa_channel,             // 'email' or 'totp'
        ]);
    }
}
