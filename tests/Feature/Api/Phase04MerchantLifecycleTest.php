<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase04MerchantLifecycleTest extends TestCase
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

    public function test_get_dmin_merchants_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/merchants');
        $response->assertStatus(200);
    }

    public function test_get_dmin_merchants_1_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/merchants/1');
        $response->assertStatus(404);
    }

    public function test_post_dmin_merchants_1_approve_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/merchants/1/approve');
        $response->assertStatus(422);
    }

    public function test_post_dmin_merchants_1_reject_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/merchants/1/reject');
        $response->assertStatus(422);
    }

    public function test_post_dmin_merchants_1_re_kyc_4()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/merchants/1/re-kyc');
        $response->assertStatus(422);
    }

    public function test_post_dmin_merchants_1_suspend_5()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/merchants/1/suspend');
        $response->assertStatus(422);
    }

    public function test_get_dmin_merchants_1_verification_logs_6()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/merchants/1/verification-logs');
        $response->assertStatus(200);
    }

    public function test_get_dmin_merchants_1_agreement_7()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/merchants/1/agreement');
        $response->assertStatus(405);
    }


}