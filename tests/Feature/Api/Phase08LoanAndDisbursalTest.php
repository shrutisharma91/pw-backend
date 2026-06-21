<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase08LoanAndDisbursalTest extends TestCase
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

    public function test_get_dmin_loans_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans');
        $response->assertStatus(200);
    }

    public function test_get_dmin_loans_1_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans/1');
        $response->assertStatus(404);
    }

    public function test_post_dmin_loans_overrides_1_force_approve_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/loans/overrides/1/force-approve');
        $response->assertStatus(422);
    }

    public function test_post_dmin_loans_overrides_1_trigger_disbursal_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/loans/overrides/1/trigger-disbursal');
        $response->assertStatus(422);
    }

    public function test_get_dmin_settlements_batches_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/settlements/batches');
        $response->assertStatus(200);
    }

    public function test_get_dmin_collections_5()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/collections');
        $response->assertStatus(200);
    }


}