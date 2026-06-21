<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase07PricingAndOffersTest extends TestCase
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

    public function test_get_dmin_pricing_emi_types_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/pricing/emi-types');
        $response->assertStatus(200);
    }

    public function test_get_dmin_pricing_tenure_slabs_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/pricing/tenure-slabs');
        $response->assertStatus(200);
    }

    public function test_get_dmin_offers_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/offers');
        $response->assertStatus(200);
    }

    public function test_post_dmin_offers_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/offers');
        $response->assertStatus(422);
    }

    public function test_get_dmin_offers_pending_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/offers/pending');
        $response->assertStatus(200);
    }

    public function test_post_dmin_offers_1_approve_5()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/offers/1/approve');
        $response->assertStatus(404);
    }


}