<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class ComplianceReportTest extends TestCase
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

    public function test_post_compliance_returns_0()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/compliance/returns');
        $response->assertStatus(422);
    }

    public function test_get_compliance_dpdp_requests_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/compliance/dpdp-requests');
        $response->assertStatus(200);
    }

    public function test_post_compliance_dpdp_requests_1_resolve_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/compliance/dpdp-requests/1/resolve');
        $response->assertStatus(422);
    }

    public function test_get_compliance_data_masking_policy_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/compliance/data-masking-policy');
        $response->assertStatus(200);
    }

    public function test_post_compliance_data_masking_policy_4()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/compliance/data-masking-policy');
        $response->assertStatus(200);
    }

    public function test_get_compliance_retention_policy_5()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/compliance/retention-policy');
        $response->assertStatus(200);
    }

    public function test_post_compliance_retention_policy_6()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/compliance/retention-policy');
        $response->assertStatus(200);
    }

    public function test_get_compliance_dashboard_7()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/compliance/dashboard');
        $response->assertStatus(200);
    }


}