<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase01AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->user = User::factory()->create([
            'mfa_verified_at' => now(),
        ]);
    }

    public function test_post_uth_login_0()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/auth/login');
        $response->assertStatus(422);
    }

    public function test_post_uth_mfa_verify_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/auth/mfa/verify');
        $response->assertStatus(422);
    }

    public function test_post_uth_forgot_password_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/auth/forgot-password');
        $response->assertStatus(422);
    }

    public function test_post_uth_reset_password_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/auth/reset-password');
        $response->assertStatus(422);
    }

    public function test_get_dmin_profile_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/profile');
        $response->assertStatus(200);
    }

    public function test_put_dmin_profile_5()
    {
        $response = $this->actingAs($this->user)->put('/api/v1/admin/profile');
        $response->assertStatus(200);
    }


}