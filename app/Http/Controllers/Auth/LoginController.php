<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| LoginController
|--------------------------------------------------------------------------
| Handles Screen 01 — Login Screen
|
| APIs:
|   POST /api/v1/auth/login    → login with email + password
|   POST /api/v1/auth/logout   → logout and invalidate token
|   POST /api/v1/auth/refresh  → get a fresh token before expiry
|
| Flow:
|   1. Validate email/password
|   2. Check if account is locked (brute-force protection)
|   3. Verify password
|   4. If MFA enabled → return mfa_required: true (frontend shows Screen 02)
|   5. If MFA disabled → return token directly
*/

class LoginController extends Controller
{
    // ------------------------------------------------------------------
    // POST /api/v1/auth/login
    // ------------------------------------------------------------------
    public function login(Request $request)
    {
        // Step 1: Validate incoming data
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Step 2: Find the user
        $user = User::where('email', $request->email)->first();

        // Step 3: Does this user exist?
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        // Step 4: Is the account active?
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been disabled. Contact support.',
            ], 403);
        }

        // Step 5: Is the account locked? (too many failed attempts)
        if ($user->isLocked()) {
            $minutesLeft = now()->diffInMinutes($user->locked_until);
            return response()->json([
                'success' => false,
                'message' => "Account locked. Try again in {$minutesLeft} minutes.",
                'locked_until' => $user->locked_until,
            ], 423);
        }

        // Step 6: Check password
        if (!Hash::check($request->password, $user->password)) {
            $user->incrementFailedLogin(); // Track failed attempt
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
                'failed_attempts' => $user->fresh()->failed_login_attempts,
            ], 401);
        }

        // Step 7: Password correct — reset failed attempts
        $user->resetFailedLogin();

        // Step 8: Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Step 9: Log this session for Screen 13 (Session Management)
        $this->logSession($user, $token);

        // Step 10: Does this user have MFA enabled?
        if ($user->mfa_enabled) {
            // Send OTP (SMS or via authenticator setup)
            app(\App\Services\MFAService::class)->sendOTP($user);

            // Tell frontend: show MFA screen (Screen 02)
            return response()->json([
                'success'      => true,
                'mfa_required' => true,
                'message'      => 'OTP sent. Please verify to continue.',
                'token'        => $token, // temp token — cannot access protected routes yet
                'user' => [
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'mfa_channel' => $user->mfa_channel, // 'totp' or 'email'
                ],
            ]);
        }

        // Step 11: No MFA — login complete, return token + user info
        return response()->json([
            'success'      => true,
            'mfa_required' => false,
            'message'      => 'Login successful.',
            'token'        => $token,
            'token_type'   => 'bearer',
            'expires_in'   => config('jwt.ttl') * 60, // seconds
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
    // POST /api/v1/auth/logout
    // ------------------------------------------------------------------
    public function logout(Request $request)
    {
        try {
            // Get the current token and invalidate it
            $token = JWTAuth::getToken();
            JWTAuth::invalidate($token);

            // Mark session as logged out in admin_sessions table
            /** @var User|null $user */
            $user = Auth::user();
            AdminSession::where('user_id', $user->id)
                ->where('is_active', true)
                ->latest()
                ->first()
                ?->update([
                    'is_active'     => false,
                    'logged_out_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not log out. Please try again.',
            ], 500);
        }
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/refresh
    // ------------------------------------------------------------------
    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json([
                'success'    => true,
                'token'      => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed. Please login again.',
            ], 401);
        }
    }

    // ------------------------------------------------------------------
    // Private helper: log login session to admin_sessions table
    // ------------------------------------------------------------------
    private function logSession(User $user, string $token): void
    {
        $payload    = JWTAuth::setToken($token)->getPayload();
        $userAgent  = request()->userAgent() ?? 'Unknown';

        AdminSession::create([
            'user_id'       => $user->id,
            'token_id'      => $payload->get('jti'), // unique token ID
            'ip_address'    => request()->ip(),
            'device_info'   => $userAgent,
            'device_type'   => $this->detectDeviceType($userAgent),
            'is_active'     => true,
            'logged_in_at'  => now(),
            'last_active_at' => now(),
        ]);
    }

    private function detectDeviceType(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android')) {
            return 'mobile';
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }
        return 'desktop';
    }
}
