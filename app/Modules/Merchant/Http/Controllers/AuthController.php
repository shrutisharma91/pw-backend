<?php

namespace App\Modules\Merchant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)
            ->whereIn('role', ['merchant_admin', 'store_manager'])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is disabled.',
            ], 403);
        }

        if ($user->mfa_enabled) {
            // Generate OTP
            $otp = '123456'; // Fixed for dev
            \App\Models\CustomerOtp::updateOrCreate(
                ['phone' => $user->email], // Using phone col as identifier for simplicity in dev
                ['otp_hash' => Hash::make($otp), 'expires_at' => now()->addMinutes(5), 'attempts' => 0]
            );

            return response()->json([
                'success' => true,
                'mfa_required' => true,
                'message' => 'MFA required. OTP sent to email.',
                'dev_otp' => app()->environment('local', 'testing') ? $otp : null
            ]);
        }

        return $this->issueToken($user);
    }

    public function verifyMfa(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();
        $record = \App\Models\CustomerOtp::where('phone', $request->email)
            ->whereNull('verified_at')
            ->first();

        if (!$record || $record->expires_at->isPast() || !Hash::check($request->otp, $record->otp_hash)) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 401);
        }

        $record->update(['verified_at' => now()]);

        return $this->issueToken($user);
    }

    private function issueToken($user)
    {
        $token = auth('api')->login($user);

        return response()->json([
            'success'      => true,
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'role'        => $user->role,
                'merchant_id' => $user->merchant_id,
                'store_id'    => $user->store_id,
            ],
        ]);
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['success' => true, 'message' => 'Successfully logged out']);
    }
}
