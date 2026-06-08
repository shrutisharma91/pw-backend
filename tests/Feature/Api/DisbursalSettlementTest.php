<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class DisbursalSettlementTest extends TestCase
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

    public function test_get_sbursals_pending_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/disbursals/pending');
        $response->assertStatus(200);
    }

    public function test_post_sbursals_trigger_batch_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/disbursals/trigger-batch');
        $response->assertStatus(422);
    }

    public function test_get_settlements_batches_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/settlements/batches');
        $response->assertStatus(200);
    }

    public function test_get_settlements_batches_1_entries_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/settlements/batches/1/entries');
        $response->assertStatus(200);
    }

    public function test_get_settlements_batches_1_download_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/settlements/batches/1/download');
        $response->assertStatus(200);
    }

    public function test_post_settlements_entries_1_dispute_5()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/settlements/entries/1/dispute');
        $response->assertStatus(422);
    }


}