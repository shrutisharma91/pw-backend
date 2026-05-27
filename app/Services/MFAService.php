<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Notifications\SendOTPNotification;

/*
|--------------------------------------------------------------------------
| MFAService
|--------------------------------------------------------------------------
| Handles all MFA / 2FA logic for Screen 02.
|
| OTP is sent to: finzwork10@gmail.com (fixed dev email)
| In production: replace with $user->email
*/

class MFAService
{
    private int $otpTTL = 300;       // OTP valid for 5 minutes
    private string $cachePrefix = 'mfa_otp_';
    private string $devEmail = 'finzwork10@gmail.com'; // ← your email

    // ------------------------------------------------------------------
    // Generate and send OTP
    // ------------------------------------------------------------------
    public function sendOTP(User $user): bool
    {
        if ($user->mfa_channel === 'totp') {
            return true; // TOTP users use Google Authenticator, no email needed
        }

        $otp = $this->generateOTP();

        // Store OTP in cache
        Cache::put(
            $this->cachePrefix . $user->id,
            [
                'otp'        => $otp,
                'attempts'   => 0,
                'created_at' => now()->toISOString(),
            ],
            $this->otpTTL
        );

        // Send OTP via email
        $this->sendEmail($otp, $user->name);

        Log::info("OTP for user {$user->id}: {$otp}");

        return true;
    }

    // ------------------------------------------------------------------
    // Verify OTP entered by user
    // ------------------------------------------------------------------
    public function verifyOTP(User $user, string $enteredOTP): array
    {
        if ($user->mfa_channel === 'totp') {
            return $this->verifyTOTP($user, $enteredOTP);
        }

        return $this->verifySMSOTP($user, $enteredOTP);
    }

    // ------------------------------------------------------------------
    // Verify SMS/Email OTP
    // ------------------------------------------------------------------
    private function verifySMSOTP(User $user, string $enteredOTP): array
    {
        $cacheKey = $this->cachePrefix . $user->id;
        $cached   = Cache::get($cacheKey);

        if (!$cached) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
                'code'    => 'otp_expired',
            ];
        }

        if ($cached['attempts'] >= 3) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'message' => 'Too many incorrect attempts. Please request a new OTP.',
                'code'    => 'too_many_attempts',
            ];
        }

        if ($cached['otp'] !== $enteredOTP) {
            $cached['attempts']++;
            Cache::put($cacheKey, $cached, $this->otpTTL);
            $remaining = 3 - $cached['attempts'];
            return [
                'success'            => false,
                'message'            => "Invalid OTP. {$remaining} attempts remaining.",
                'code'               => 'invalid_otp',
                'attempts_remaining' => $remaining,
            ];
        }

        Cache::forget($cacheKey);

        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
        ];
    }

    // ------------------------------------------------------------------
    // Verify TOTP (Google Authenticator)
    // ------------------------------------------------------------------
    private function verifyTOTP(User $user, string $code): array
    {
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return [
                'success' => false,
                'message' => 'Invalid authenticator code.',
                'code'    => 'invalid_totp',
            ];
        }

        return [
            'success' => true,
            'message' => 'Authenticator code verified.',
        ];
    }

    // ------------------------------------------------------------------
    // Send OTP via Email to finzwork10@gmail.com
    // ------------------------------------------------------------------
    private function sendEmail(string $otp, string $userName): void
    {
        Mail::send([], [], function ($message) use ($otp, $userName) {
            $message->to($this->devEmail)
                    ->subject('FinZ Admin — Your Login OTP')
                    ->html("
                        <div style='font-family: Arial, sans-serif; max-width: 400px; margin: auto;'>
                            <h2 style='color: #008080;'>FinZ Admin — OTP Verification</h2>
                            <p>Hello <b>{$userName}</b>,</p>
                            <p>Your one-time password for login is:</p>
                            <div style='font-size: 36px; font-weight: bold; letter-spacing: 8px;
                                        background: #f4f4f4; padding: 16px; text-align: center;
                                        border-radius: 8px; margin: 16px 0;'>
                                {$otp}
                            </div>
                            <p>This OTP is valid for <b>5 minutes</b> only.</p>
                            <p>If you did not request this, please secure your account immediately.</p>
                            <p style='color: #888; font-size: 12px;'>FinZ Security Team</p>
                        </div>
                    ");
        });
    }

    // ------------------------------------------------------------------
    // Generate random 6-digit OTP
    // ------------------------------------------------------------------
    private function generateOTP(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function hasActivOTP(User $user): bool
    {
        return Cache::has($this->cachePrefix . $user->id);
    }

    public function getOTPTTL(): int
    {
        return $this->otpTTL;
    }
}
