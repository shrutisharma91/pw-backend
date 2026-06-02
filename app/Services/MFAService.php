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

        // Send OTP via email notification
        $user->notify(new SendOTPNotification($otp));

        Log::info("OTP for user {$user->id}: {$otp}");

        return true;
    }

    // ------------------------------------------------------------------
    // Verify OTP entered by user
    // ------------------------------------------------------------------
    public function verifyOTP(User $user, string $enteredOTP): array
    {
        // First check: Is it a backup recovery code?
        if (strlen($enteredOTP) !== 6 && !empty($user->mfa_recovery_codes)) {
            $codes = $user->mfa_recovery_codes;
            if (in_array($enteredOTP, $codes)) {
                // Remove the used recovery code
                $newCodes = array_values(array_diff($codes, [$enteredOTP]));
                $user->update(['mfa_recovery_codes' => $newCodes]);

                return [
                    'success' => true,
                    'message' => 'Backup recovery code verified. MFA bypassed for this login.',
                ];
            }
        }

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
        $secret = $user->mfa_secret;
        if (!$secret) {
            return [
                'success' => false,
                'message' => 'TOTP is not configured for this user.',
                'code'    => 'totp_not_configured',
            ];
        }

        if ($this->verifyTOTPCode($secret, $code)) {
            return [
                'success' => true,
                'message' => 'Authenticator code verified.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid authenticator code.',
            'code'    => 'invalid_totp',
        ];
    }

    // ------------------------------------------------------------------
    // TOTP Core Verification Functions
    // ------------------------------------------------------------------
    public function verifyTOTPCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $key = $this->base32Decode($secret);
        $timeWindow = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $timeStep = $timeWindow + $i;
            // Pack time step to 64-bit binary (8 bytes)
            $timePacked = pack('N*', 0) . pack('N*', $timeStep);
            
            // Compute HMAC-SHA1
            $hash = hash_hmac('sha1', $timePacked, $key, true);
            
            // Dynamic truncation
            $offset = ord($hash[19]) & 0x0F;
            $truncated = unpack('N', substr($hash, $offset, 4));
            $otp = $truncated[1] & 0x7FFFFFFF;
            $otpCode = str_pad($otp % 1000000, 6, '0', STR_PAD_LEFT);

            if (hash_equals($otpCode, $code)) {
                return true;
            }
        }

        return false;
    }

    public function generateSecretKey(int $length = 16): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf(
                "%04x-%04x",
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
        }
        return $codes;
    }

    private function base32Decode(string $base32): string
    {
        $base32 = strtoupper($base32);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bufferSize = 0;
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            if ($char === '=') {
                break;
            }
            $val = strpos($alphabet, $char);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $binary .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }

        return $binary;
    }



    // ------------------------------------------------------------------
    // Generate random 6-digit OTP
    // ------------------------------------------------------------------
    private function generateOTP(): string
    {
        if (app()->environment('local')) {
            return '123456';
        }
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
