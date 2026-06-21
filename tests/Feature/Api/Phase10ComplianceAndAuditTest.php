<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase10ComplianceAndAuditTest extends TestCase
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

    public function test_get_dmin_audit_trails_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/audit-trails');
        $response->assertStatus(200);
    }

    public function test_get_dmin_consents_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/consents');
        $response->assertStatus(200);
    }

    public function test_post_dmin_compliance_returns_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/compliance/returns');
        $response->assertStatus(422);
    }

    public function test_get_dmin_compliance_dpdp_requests_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/compliance/dpdp-requests');
        $response->assertStatus(200);
    }


}