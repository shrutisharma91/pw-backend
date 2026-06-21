<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase11AnalyticsAndBITest extends TestCase
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

    public function test_get_dmin_analytics_business_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/analytics/business');
        $response->assertStatus(200);
    }

    public function test_get_dmin_analytics_lender_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/analytics/lender');
        $response->assertStatus(200);
    }

    public function test_get_dmin_analytics_sales_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/analytics/sales');
        $response->assertStatus(200);
    }

    public function test_post_dmin_reports_custom_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/reports/custom');
        $response->assertStatus(422);
    }


}