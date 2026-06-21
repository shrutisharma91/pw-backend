<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase06LenderOperationsTest extends TestCase
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

    public function test_get_dmin_lenders_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/lenders');
        $response->assertStatus(200);
    }

    public function test_post_dmin_lenders_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/lenders');
        $response->assertStatus(422);
    }

    public function test_get_dmin_lenders_1_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/lenders/1');
        $response->assertStatus(404);
    }

    public function test_get_dmin_lender_sla_metrics_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/lender-sla/metrics');
        $response->assertStatus(200);
    }

    public function test_get_dmin_lender_waterfalls_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/lender-waterfalls');
        $response->assertStatus(200);
    }

    public function test_post_dmin_lender_waterfalls_simulate_5()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/lender-waterfalls/simulate');
        $response->assertStatus(422);
    }

    public function test_get_dmin_lender_rules_6()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/lender-rules');
        $response->assertStatus(200);
    }


}