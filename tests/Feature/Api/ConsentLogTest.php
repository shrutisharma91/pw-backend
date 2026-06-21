<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class ConsentLogTest extends TestCase
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

    public function test_get_consents_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/consents');
        $response->assertStatus(200);
    }

    public function test_post_consents_1_withdraw_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/consents/1/withdraw');
        $response->assertStatus(422);
    }

    public function test_get_consents_1_diff_2_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/consents/1/diff/2');
        $response->assertStatus(200);
    }

    public function test_get_consents_export_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/consents/export');
        $response->assertStatus(200);
    }


}