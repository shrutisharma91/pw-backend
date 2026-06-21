<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase12NotificationsAndDocsTest extends TestCase
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

    public function test_get_dmin_templates_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/templates');
        $response->assertStatus(200);
    }

    public function test_get_dmin_communication_logs_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/communication-logs');
        $response->assertStatus(200);
    }

    public function test_get_dmin_documents_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/documents');
        $response->assertStatus(200);
    }


}