<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SendOTPNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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
    private int $otpTTL = 300;           // OTP valid for 5 minutes
    private int $resendCooldown = 60;    // Min seconds between resend requests
    private string $cachePrefix = 'mfa_otp_';
    private string $devEmail = 'finzwork10@gmail.com';
    private string $setupCachePrefix = 'mfa_setup_'; // pending TOTP reconfiguration secret
    private int $setupTTL = 600;         // Pending TOTP setup valid for 10 minutes

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

        // Send OTP via email notification (dev: fixed inbox; production: use $user->email)
        Notification::route('mail', $this->devEmail)
            ->notify(new SendOTPNotification($otp));

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

    // ------------------------------------------------------------------
    // Profile Settings — MFA reconfiguration & recovery codes
    // ------------------------------------------------------------------

    /**
     * Begin a TOTP (authenticator app) reconfiguration.
     * The secret is held in cache (not persisted) until the user proves
     * they can generate a valid code via confirmTotpSetup().
     *
     * @return array{secret: string, otpauth_uri: string, expires_in: int}
     */
    public function beginTotpSetup(User $user): array
    {
        $secret = $this->generateSecretKey();

        Cache::put($this->setupCachePrefix . $user->id, $secret, $this->setupTTL);

        return [
            'secret'      => $secret,
            'otpauth_uri' => $this->buildOtpAuthUri($user, $secret),
            'expires_in'  => $this->setupTTL,
        ];
    }

    /**
     * Confirm a pending TOTP setup. On success TOTP becomes the active MFA
     * channel and a fresh set of recovery codes is issued (shown once).
     *
     * @return array{success: bool, message?: string, code?: string, recovery_codes?: array<int, string>}
     */
    public function confirmTotpSetup(User $user, string $code): array
    {
        $secret = Cache::get($this->setupCachePrefix . $user->id);

        if (! $secret) {
            return [
                'success' => false,
                'message' => 'MFA setup session has expired. Please restart the setup.',
                'code'    => 'setup_expired',
            ];
        }

        if (! $this->verifyTOTPCode($secret, $code)) {
            return [
                'success' => false,
                'message' => 'Invalid authenticator code. Please try again.',
                'code'    => 'invalid_totp',
            ];
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'mfa_enabled'        => true,
            'mfa_channel'        => 'totp',
            'mfa_secret'         => $secret,
            'mfa_recovery_codes' => $recoveryCodes,
        ]);

        Cache::forget($this->setupCachePrefix . $user->id);

        return [
            'success'        => true,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Switch the active MFA channel back to email OTP and drop any TOTP secret.
     */
    public function switchToEmailChannel(User $user): void
    {
        Cache::forget($this->setupCachePrefix . $user->id);

        $user->update([
            'mfa_channel' => 'email',
            'mfa_secret'  => null,
        ]);
    }

    /**
     * Issue a fresh set of recovery codes, invalidating any previous ones.
     *
     * @return array<int, string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->update(['mfa_recovery_codes' => $codes]);

        return $codes;
    }

    public function recoveryCodesRemaining(User $user): int
    {
        return is_array($user->mfa_recovery_codes) ? count($user->mfa_recovery_codes) : 0;
    }

    /**
     * Build an otpauth:// provisioning URI consumable by authenticator apps
     * (Google Authenticator, Authy, etc.) and renderable as a QR code.
     */
    public function buildOtpAuthUri(User $user, string $secret): string
    {
        $issuer  = config('app.name', 'FinZ Admin');
        $account = $user->email ?: ('user-' . $user->id);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer)
        );
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

    public function isResendBlocked(User $user): bool
    {
        $cached = Cache::get($this->cachePrefix . $user->id);

        if (! $cached || empty($cached['created_at'])) {
            return false;
        }

        $sentAt = \Carbon\Carbon::parse($cached['created_at']);

        return $sentAt->isAfter(now()->subSeconds($this->resendCooldown));
    }

    public function getOTPTTL(): int
    {
        return $this->otpTTL;
    }

    public function getResendCooldown(): int
    {
        return $this->resendCooldown;
    }
}
