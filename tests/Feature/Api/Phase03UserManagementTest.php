<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase03UserManagementTest extends TestCase
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

    public function test_get_dmin_users_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/users');
        $response->assertStatus(200);
    }

    public function test_post_dmin_users_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/users');
        $response->assertStatus(422);
    }

    public function test_get_dmin_users_1_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/users/1');
        $response->assertStatus(200);
    }

    public function test_put_dmin_users_1_3()
    {
        $response = $this->actingAs($this->user)->put('/api/v1/admin/users/1');
        $response->assertStatus(200);
    }

    public function test_post_dmin_users_1_disable_4()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/users/1/disable');
        $response->assertStatus(422);
    }

    public function test_get_dmin_roles_5()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/roles');
        $response->assertStatus(200);
    }

    public function test_post_dmin_roles_6()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/roles');
        $response->assertStatus(422);
    }

    public function test_put_dmin_permissions_roles_1_7()
    {
        $response = $this->actingAs($this->user)->put('/api/v1/admin/permissions/roles/1');
        $response->assertStatus(422);
    }

    public function test_get_dmin_sessions_8()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/sessions');
        $response->assertStatus(200);
    }

    public function test_post_dmin_sessions_1_revoke_9()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/sessions/1/revoke');
        $response->assertStatus(404);
    }


}