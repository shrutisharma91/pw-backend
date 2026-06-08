<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class ManualOverrideTest extends TestCase
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

    public function test_post_loans_overrides_1_force_approve_0()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/loans/overrides/1/force-approve');
        $response->assertStatus(422);
    }

    public function test_post_loans_overrides_1_override_rejection_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/loans/overrides/1/override-rejection');
        $response->assertStatus(422);
    }

    public function test_post_loans_overrides_1_trigger_disbursal_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/loans/overrides/1/trigger-disbursal');
        $response->assertStatus(422);
    }

    public function test_post_loans_overrides_1_refund_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/loans/overrides/1/refund');
        $response->assertStatus(422);
    }


}