<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| MFAVerified Middleware
|--------------------------------------------------------------------------
| Applied to all protected routes (Screen 04, 05 and beyond).
|
| After login, the user gets a JWT token but if MFA is enabled,
| they must complete Screen 02 verification before accessing anything.
|
| This middleware checks: has MFA been verified in the last 8 hours?
| If not → return 403 with mfa_required: true
| Frontend will then redirect to MFA verification screen.
*/

class MFAVerified
{
    public function handle(Request $request, Closure $next)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // MFA is permanently enabled — check if they've verified it recently
        if (!$user->hasMFAVerifiedThisSession()) {
            return response()->json([
                'success'      => false,
                'mfa_required' => true,
                'message'      => 'MFA verification required. Please verify your identity.',
            ], 403);
        }

        return $next($request);
    }
}
