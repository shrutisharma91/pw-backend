<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase14SupportAndHelpdeskTest extends TestCase
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

    public function test_get_dmin_tickets_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/tickets');
        $response->assertStatus(200);
    }

    public function test_get_dmin_tickets_1_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/tickets/1');
        $response->assertStatus(404);
    }

    public function test_post_dmin_tickets_1_resolve_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/tickets/1/resolve');
        $response->assertStatus(404);
    }


}