<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class RiskRuleTest extends TestCase
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

    public function test_get_risk_rules_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/risk-rules');
        $response->assertStatus(200);
    }

    public function test_post_risk_rules_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/risk-rules');
        $response->assertStatus(422);
    }

    public function test_put_risk_rules_1_2()
    {
        $response = $this->actingAs($this->user)->put('/api/v1/admin/risk-rules/1');
        $response->assertStatus(404);
    }

    public function test_post_risk_rules_simulate_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/risk-rules/simulate');
        $response->assertStatus(200);
    }


}