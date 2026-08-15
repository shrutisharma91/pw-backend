<?php

namespace App\Modules\Pos\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosAuthController extends PosBaseController
{
    /**
     * Authenticate store manager and issue JWT.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $token = Auth::guard('api')->attempt($credentials);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $user = Auth::guard('api')->user();

        // Ensure user is a store manager or has store access
        if (!in_array(strtolower((string) $user->role), ['store_manager', 'merchant_admin', 'superadmin', 'super_admin'])) {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. This portal is for store staff.',
            ], 403);
        }

        if ($user->mfa_enabled) {
            // Generate OTP
            $otp = '123456'; // Fixed for dev
            \App\Models\CustomerOtp::updateOrCreate(
                ['phone' => $user->email], // Using phone col as identifier for simplicity in dev
                ['otp_hash' => \Illuminate\Support\Facades\Hash::make($otp), 'expires_at' => now()->addMinutes(5), 'attempts' => 0]
            );

            return response()->json([
                'success' => true,
                'mfa_required' => true,
                'message' => 'MFA required. OTP sent to email.',
                'dev_otp' => app()->environment('local', 'testing') ? $otp : null
            ]);
        }

        return $this->issueToken($user, $token, $request);
    }

    public function verifyMfa(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $request->email)->firstOrFail();
        $record = \App\Models\CustomerOtp::where('phone', $request->email)
            ->whereNull('verified_at')
            ->first();

        if (!$record || $record->expires_at->isPast() || !\Illuminate\Support\Facades\Hash::check($request->otp, $record->otp_hash)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 401);
        }

        $record->update(['verified_at' => now()]);
        
        $token = Auth::guard('api')->login($user);

        return $this->issueToken($user, $token, $request);
    }

    private function issueToken($user, $token, $request)
    {
        $user->load('merchant'); // Load merchant details

        // Get the JWT token ID (jti) to create an AdminSession (AdminAuth middleware requires this)
        $payload = \PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
        $jti = $payload->get('jti');

        \App\Models\AdminSession::create([
            'user_id' => $user->id,
            'token_id' => $jti,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() ?? 'Unknown',
            'logged_in_at' => now(),
            'last_active_at' => now(),
            'is_active' => true,
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Login successful.',
            'access_token' => $token,
            'user'         => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'role'     => $user->role,
                'merchant' => $user->merchant,
                'store_ids'=> $user->store_ids,
            ]
        ]);
    }

    /**
     * Get authenticated user info.
     */
    public function me(Request $request)
    {
        $user = auth('api')->user();
        $user->load('merchant');
        
        return response()->json([
            'success' => true,
            'data'    => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'role'     => $user->role,
                'merchant' => $user->merchant,
                'store_ids'=> $user->store_ids,
                'active_store' => $this->getStoreId($request),
            ]
        ]);
    }

    /**
     * Invalidate JWT.
     */
    public function logout()
    {
        Auth::guard('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ]);
    }
}
