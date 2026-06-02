<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/*
|--------------------------------------------------------------------------
| PasswordResetController
|--------------------------------------------------------------------------
| Handles Screen 03 — Forgot Password & Reset
|
| APIs:
|   POST /api/v1/auth/forgot-password  → send reset link to email
|   POST /api/v1/auth/reset-password   → set new password using token
|
| Flow:
|   1. User enters email on Screen 03
|   2. We generate a random token, store it in password_reset_tokens table
|   3. Email is sent with a link: https://yourdomain.com/reset?token=xxx
|   4. User clicks link, frontend shows new password form
|   5. Frontend calls reset-password with token + new password
|   6. We verify token, update password, invalidate all sessions
*/

class PasswordResetController extends Controller
{
    // ------------------------------------------------------------------
    // POST /api/v1/auth/forgot-password
    // ------------------------------------------------------------------
    #[OA\Post(
        path: "/api/v1/auth/forgot-password",
        summary: "Send Password Reset Link",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "finzwork10@gmail.com")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Find user by email
        $user = User::where('email', $request->email)->first();

        // IMPORTANT: Always return success even if email not found
        // This prevents attackers from knowing which emails exist
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If this email exists, a password reset code has been sent.',
            ]);
        }

        // Delete any existing reset tokens for this email
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Generate a secure 6-digit code
        $code = sprintf('%06d', random_int(0, 999999));

        // Store token in database (expires in 15 minutes — as per your spec)
        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($code),
            'created_at' => now()->toIso8601String(),
        ]);

        // Send email
        Mail::to($user->email)->send(new \App\Mail\PasswordResetCode($user->name, $code));

        $response = [
            'success' => true,
            'message' => 'If this email exists, a password reset code has been sent.',
            'expires_in_minutes' => 15,
        ];

        if (app()->environment('local')) {
            $response['debug_token'] = $token;
        }

        return response()->json($response);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/auth/reset-password
    // ------------------------------------------------------------------
    #[OA\Post(
        path: "/api/v1/auth/reset-password",
        summary: "Reset Password",
        tags: ["Auth"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "finzwork10@gmail.com"),
                    new OA\Property(property: "token", type: "string", example: "your-reset-token-here"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "New@password123"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "New@password123")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Success")
        ]
    )]
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required_without:code|string',
            'code'                  => 'required_without:token|string',
            'password'              => [
                'required',
                'string',
                'min:8',
                'confirmed',             // requires password_confirmation field
                'regex:/[A-Z]/',         // must have uppercase
                'regex:/[0-9]/',         // must have number
                'regex:/[@$!%*?&]/',     // must have special character
            ],
            'password_confirmation' => 'required',
        ], [
            'password.regex' => 'Password must include uppercase, number, and special character (@$!%*?&).',
        ]);

        // Find the reset token record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // Does a reset request exist for this email?
        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 422);
        }

        // Has the token expired? (15 minutes)
        if (\Illuminate\Support\Carbon::parse($resetRecord->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'message' => 'Reset code has expired. Please request a new one.',
                'code'    => 'token_expired',
            ], 422);
        }

        // Get the token/code parameter
        $token = $request->input('token') ?? $request->input('code');

        // Is the token correct?
        if (!Hash::check($token, $resetRecord->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code.',
            ], 422);
        }

        // Find user
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Check password history (per spec: can't reuse last 5 passwords)
        $pastPasswords = $user->passwordHistories()->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($pastPasswords as $pastPassword) {
            if (Hash::check($request->password, $pastPassword->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot reuse any of your last 5 passwords.',
                    'code'    => 'password_reuse_blocked',
                ], 422);
            }
        }

        // Update password
        $user->update([
            'password'            => Hash::make($request->password),
            'password_changed_at' => now(),
        ]);

        // Save password history
        $user->passwordHistories()->create([
            'password' => Hash::make($request->password),
        ]);

        // Delete the used reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Invalidate ALL active sessions for this user (per your spec)
        // This logs them out from all devices
        $user->sessions()->where('is_active', true)->update([
            'is_active'     => false,
            'logged_out_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ]);
    }
}
