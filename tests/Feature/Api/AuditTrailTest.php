<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class AuditTrailTest extends TestCase
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

    public function test_get_udit_trails_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/audit-trails');
        $response->assertStatus(200);
    }

    public function test_get_udit_trails_export_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/audit-trails/export');
        $response->assertStatus(200);
    }

    public function test_get_udit_trails_anomalies_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/audit-trails/anomalies');
        $response->assertStatus(200);
    }

    public function test_post_udit_trails_verify_hash_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/audit-trails/verify-hash');
        $response->assertStatus(200);
    }


}