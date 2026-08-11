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
