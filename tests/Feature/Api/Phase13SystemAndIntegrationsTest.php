<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase13SystemAndIntegrationsTest extends TestCase
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

    public function test_get_dmin_workflows_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/workflows');
        $response->assertStatus(200);
    }

    public function test_get_dmin_integrations_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/integrations');
        $response->assertStatus(200);
    }

    public function test_get_dmin_feature_flags_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/feature-flags');
        $response->assertStatus(200);
    }

    public function test_get_dmin_system_parameters_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/system/parameters');
        $response->assertStatus(200);
    }

    public function test_put_dmin_system_maintenance_4()
    {
        $response = $this->actingAs($this->user)->put('/api/v1/admin/system/maintenance');
        $response->assertStatus(200);
    }


}