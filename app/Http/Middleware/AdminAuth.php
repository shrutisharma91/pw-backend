<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

/*
|--------------------------------------------------------------------------
| AdminAuth Middleware
|--------------------------------------------------------------------------
| Applied to every protected route.
| Checks that:
|   1. A valid JWT token exists in the Authorization header
|   2. The user account is still active (not disabled by Super Admin)
|   3. The user has the correct role to access admin panel
|
| How to use in routes/api.php:
|   Route::middleware(['auth:api'])->group(...)
|
| This middleware runs BEFORE MFAVerified middleware.
| Order: AdminAuth → MFAVerified → Controller
*/

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Try to get the authenticated user from the JWT token
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 401);
            }

            // Check if account is still active
            // Super Admin can disable any user from Screen 09 (User Directory)
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been disabled. Please contact support.',
                    'code'    => 'account_disabled',
                ], 403);
            }

            // Verify if the specific session is still active (not revoked)
            $payload = JWTAuth::parseToken()->getPayload();
            $jti = $payload->get('jti');
            $session = \App\Models\AdminSession::where('token_id', $jti)->first();

            if (!$session || !$session->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session has been revoked or expired.',
                    'code'    => 'session_revoked',
                ], 401);
            }

            // Check IP rules for user's role
            if (!\App\Models\RoleConfig::checkIPRules($user->role, $request->ip())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied from this IP address.',
                    'code'    => 'ip_restricted',
                ], 403);
            }

            // Update last active timestamp
            $session->update(['last_active_at' => now()]);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Session expired. Please login again.',
                'code'    => 'token_expired',
            ], 401);

        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token.',
                'code'    => 'token_invalid',
            ], 401);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token not provided.',
                'code'    => 'token_missing',
            ], 401);
        }

        return $next($request);
    }
}
