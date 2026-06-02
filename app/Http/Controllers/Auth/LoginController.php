<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminSession;
use App\Models\RoleConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| LoginController
|--------------------------------------------------------------------------
| Handles Screen 01 — Login Screen
|
| APIs:
|   POST /api/v1/auth/login    → login with email + password
|   POST /api/v1/auth/logout   → logout and invalidate token
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
    #[OA\Post(
        path: "/api/v1/auth/login",
        summary: "Login Admin",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "finzwork10@gmail.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "New@password123")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
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

        // Step 3.5: Check IP rules for user's role before proceeding
        if (!RoleConfig::checkIPRules($user->role, $request->ip())) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied from this IP address.',
            ], 403);
        }

        // Step 3.6: Check failed attempts CAPTCHA requirement
        $ip = $request->ip();
        $email = $request->email;
        $cacheKey = "login_attempts:{$ip}_" . md5($email);
        $attempts = Cache::get($cacheKey, 0);

        $captchaRequired = $attempts >= 3;

        if ($captchaRequired) {
            if (!$request->has('captcha_key') || !$request->has('captcha_answer')) {
                $captchaKey = (string) \Illuminate\Support\Str::uuid();
                $num1 = rand(1, 9);
                $num2 = rand(1, 9);
                $answer = (string)($num1 + $num2);
                Cache::put("captcha:{$captchaKey}", $answer, 300);

                return response()->json([
                    'success' => false,
                    'captcha_required' => true,
                    'captcha_key' => $captchaKey,
                    'captcha_question' => "{$num1} + {$num2}",
                    'message' => 'CAPTCHA verification required after multiple failed login attempts.',
                ], 403);
            }

            $cachedAnswer = Cache::get("captcha:{$request->captcha_key}");
            if (!$cachedAnswer || $cachedAnswer !== $request->captcha_answer) {
                $captchaKey = (string) \Illuminate\Support\Str::uuid();
                $num1 = rand(1, 9);
                $num2 = rand(1, 9);
                $answer = (string)($num1 + $num2);
                Cache::put("captcha:{$captchaKey}", $answer, 300);

                return response()->json([
                    'success' => false,
                    'captcha_required' => true,
                    'captcha_key' => $captchaKey,
                    'captcha_question' => "{$num1} + {$num2}",
                    'message' => 'Invalid or expired CAPTCHA answer.',
                ], 403);
            }
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
            $user->incrementFailedLogin(); // Track failed attempt in DB for account lockout

            // Track failed attempt in cache for CAPTCHA triggering
            $attempts++;
            Cache::put($cacheKey, $attempts, 600); // 10 minutes TTL

            $response = [
                'success' => false,
                'message' => 'Invalid email or password.',
                'failed_attempts' => $user->fresh()->failed_login_attempts,
            ];

            if ($attempts >= 3) {
                $captchaKey = (string) \Illuminate\Support\Str::uuid();
                $num1 = rand(1, 9);
                $num2 = rand(1, 9);
                $answer = (string)($num1 + $num2);
                Cache::put("captcha:{$captchaKey}", $answer, 300);

                $response['captcha_required'] = true;
                $response['captcha_key'] = $captchaKey;
                $response['captcha_question'] = "{$num1} + {$num2}";
            }

            return response()->json($response, 401);
        }

        // Step 7: Password correct — reset failed attempts
        $user->resetFailedLogin();
        Cache::forget($cacheKey);

        // Step 7.5: Enforce concurrent session limits per role
        $roleConfig = RoleConfig::where('role_name', $user->role)->first();
        $sessionLimit = $roleConfig ? $roleConfig->concurrent_session_limit : 5;

        $activeSessionsCount = AdminSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->count();

        if ($activeSessionsCount >= $sessionLimit) {
            // Revoke oldest active session(s)
            $oldestSessions = AdminSession::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('logged_in_at', 'asc')
                ->limit($activeSessionsCount - $sessionLimit + 1)
                ->get();

            foreach ($oldestSessions as $oldSession) {
                $oldSession->update([
                    'is_active' => false,
                    'logged_out_at' => now(),
                    'suspicious_reason' => 'Terminated due to concurrent session limit per role.'
                ]);
            }
        }

        // Step 8: Generate JWT token
        $token = JWTAuth::fromUser($user);

        // Step 9: Log this session for Screen 13 (Session Management)
        $this->logSession($user, $token);

        // Step 10: MFA is permanently enabled for all users.
        // Send OTP
        app(\App\Services\MFAService::class)->sendOTP($user);

        // Tell frontend: show MFA screen (Screen 02)
        return response()->json([
            'success'      => true,
            'mfa_required' => true,
            'message'      => 'OTP sent. Please verify to continue.',
            'access_token' => $token, // temp token — cannot access protected routes yet
            'user' => [
                'name'        => $user->name,
                'email'       => $user->email,
                'mfa_channel' => $user->mfa_channel ?? 'email',
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/logout
    // ------------------------------------------------------------------
    #[OA\Post(
        path: "/api/v1/auth/logout",
        summary: "Logout Admin",
        security: [["sanctum" => []]],
        tags: ["Auth"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
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
    #[OA\Post(
        path: "/api/v1/auth/refresh",
        summary: "Refresh Token",
        security: [["sanctum" => []]],
        tags: ["Auth"],
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
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

        // Check for suspicious login indicators
        $lastSession = AdminSession::where('user_id', $user->id)
            ->orderBy('logged_in_at', 'desc')
            ->first();

        $isSuspicious = false;
        $suspiciousReason = null;

        if ($lastSession) {
            if ($lastSession->ip_address !== request()->ip()) {
                $isSuspicious = true;
                $suspiciousReason = 'IP address changed from ' . $lastSession->ip_address;
            } elseif ($lastSession->device_info !== $userAgent) {
                $isSuspicious = true;
                $suspiciousReason = 'Device/browser changed';
            }
        }

        $hour = (int) now()->format('H');
        if ($hour >= 23 || $hour < 5) {
            $isSuspicious = true;
            $suspiciousReason = ($suspiciousReason ? $suspiciousReason . '; ' : '') . 'Login during unusual hours (off-work)';
        }

        AdminSession::create([
            'user_id'       => $user->id,
            'token_id'      => $payload->get('jti'), // unique token ID
            'ip_address'    => request()->ip(),
            'device_info'   => $userAgent,
            'device_type'   => $this->detectDeviceType($userAgent),
            'is_active'     => true,
            'is_suspicious' => $isSuspicious,
            'suspicious_reason' => $suspiciousReason,
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
