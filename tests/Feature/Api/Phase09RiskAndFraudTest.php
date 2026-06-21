<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase09RiskAndFraudTest extends TestCase
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

    public function test_get_dmin_fraud_alerts_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/fraud-alerts');
        $response->assertStatus(200);
    }

    public function test_get_dmin_blacklist_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/blacklist');
        $response->assertStatus(200);
    }

    public function test_post_dmin_blacklist_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/blacklist');
        $response->assertStatus(422);
    }

    public function test_post_dmin_blacklist_1_remove_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/blacklist/1/remove');
        $response->assertStatus(422);
    }

    public function test_get_dmin_risk_rules_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/risk-rules');
        $response->assertStatus(200);
    }

    public function test_get_dmin_manual_reviews_5()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/manual-reviews');
        $response->assertStatus(200);
    }


}