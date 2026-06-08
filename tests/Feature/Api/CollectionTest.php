<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class CollectionTest extends TestCase
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

    public function test_get_collections_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/collections');
        $response->assertStatus(200);
    }

    public function test_post_collections_1_assign_agent_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/collections/1/assign-agent');
        $response->assertStatus(422);
    }

    public function test_get_collections_bounces_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/collections/bounces');
        $response->assertStatus(200);
    }

    public function test_post_collections_bounces_1_retry_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/collections/bounces/1/retry');
        $response->assertStatus(404);
    }

    public function test_post_collections_1_npa_status_4()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/collections/1/npa-status');
        $response->assertStatus(422);
    }


}