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
        $response->assertStatus(200)
            ->assertJsonPath('data.security.0.key', 'otp_expiry_minutes')
            ->assertJsonPath('data.security.0.value', 5)
            ->assertJsonPath('data.platform.3.key', 'platform_name');
    }

    public function test_get_system_parameters_debug_logging_status()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/system/parameters/debug-logging');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['enabled']]);
    }

    public function test_put_system_parameters_debug_logging_toggle()
    {
        $response = $this->actingAs($this->user)->putJson('/api/v1/admin/system/parameters/debug-logging', [
            'enabled' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.enabled', true);

        $this->actingAs($this->user)->putJson('/api/v1/admin/system/parameters/debug-logging', [
            'enabled' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.enabled', false);
    }

    public function test_post_system_parameters_reset_to_defaults()
    {
        $this->actingAs($this->user)->putJson('/api/v1/admin/system/parameters', [
            'parameters' => [
                ['key' => 'otp_expiry_minutes', 'value' => 99],
            ],
        ])->assertStatus(200);

        $response = $this->actingAs($this->user)->post('/api/v1/admin/system/parameters/reset');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['security', 'platform']]);

        $this->assertDatabaseHas('system_parameters', [
            'key'   => 'otp_expiry_minutes',
            'value' => '5',
        ]);
    }

    public function test_put_dmin_system_maintenance_4()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/system/maintenance', [
            'enabled' => false,
        ]);
        $response->assertStatus(200);
    }


}