<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class FraudAlertTest extends TestCase
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

    public function test_get_fraud_alerts_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/fraud-alerts');
        $response->assertStatus(200);
    }

    public function test_post_fraud_alerts_1_block_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/fraud-alerts/1/block');
        $response->assertStatus(404);
    }

    public function test_post_fraud_alerts_1_unblock_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/fraud-alerts/1/unblock');
        $response->assertStatus(404);
    }

    public function test_post_fraud_alerts_1_escalate_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/fraud-alerts/1/escalate');
        $response->assertStatus(404);
    }

    public function test_get_fraud_alerts_stats_heatmap_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/fraud-alerts/stats/heatmap');
        $response->assertStatus(200);
    }


}