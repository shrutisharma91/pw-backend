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

    public function test_post_admin_tickets_create()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/admin/tickets', [
            'subject'     => 'Test ticket from API',
            'description' => 'Customer cannot download agreement PDF.',
            'priority'    => 'high',
            'category'    => 'agreement',
            'entity_type' => 'merchant',
            'entity_id'   => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subject', 'Test ticket from API')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.category', 'agreement')
            ->assertJsonStructure(['data' => ['ticket_number', 'created_by', 'created_at']]);

        $this->assertDatabaseHas('tickets', [
            'subject'  => 'Test ticket from API',
            'status'   => 'open',
            'priority' => 'high',
        ]);

        $this->assertDatabaseHas('ticket_messages', [
            'body' => 'Customer cannot download agreement PDF.',
        ]);
    }

    public function test_post_admin_tickets_create_requires_subject_and_description()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/admin/tickets', [
            'priority' => 'low',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'description']);
    }

    public function test_post_admin_tickets_create_with_assignee_sets_in_progress()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/admin/tickets', [
            'subject'     => 'Assigned ticket',
            'description' => 'Needs immediate review.',
            'assigned_to' => $this->user->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assigned_to', $this->user->id);
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