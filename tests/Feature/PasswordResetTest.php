<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_six_digit_code()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'user@example.com',
            'name' => 'John Doe',
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'If this email exists, a password reset code has been sent.',
            'expires_in_minutes' => 15,
        ]);

        // Check code exists in database
        $record = DB::table('password_reset_tokens')->where('email', 'user@example.com')->first();
        $this->assertNotNull($record);

        // Check email was sent with 6-digit code
        Mail::assertSent(\App\Mail\PasswordResetCode::class, function ($mail) {
            return $mail->hasTo('user@example.com') && strlen($mail->code) === 6;
        });
    }

    public function test_reset_password_with_valid_code()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $code = '123456';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($code),
            'created_at' => now(),
        ]);

        // Test with "code" parameter
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'code' => $code,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ]);

        // Assert token is deleted
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'user@example.com']);

        // Assert password changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    public function test_reset_password_with_token_parameter()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $code = '654321';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($code),
            'created_at' => now(),
        ]);

        // Test with "token" parameter
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $code,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ]);
    }

    public function test_reset_password_with_invalid_code_fails()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'code' => '999999',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid reset code.',
        ]);
    }

    public function test_reset_password_with_expired_code_fails()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make('123456'),
            'created_at' => now()->subMinutes(16),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'code' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Reset code has expired. Please request a new one.',
            'code' => 'token_expired',
        ]);
    }

    public function test_cannot_reuse_last_5_passwords()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('CurrentPassword123!'),
        ]);

        // Add history
        $user->passwordHistories()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $code = '123456';
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($code),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'user@example.com',
            'code' => $code,
            'password' => 'OldPassword123!',
            'password_confirmation' => 'OldPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'You cannot reuse any of your last 5 passwords.',
            'code' => 'password_reuse_blocked',
        ]);
    }
}
