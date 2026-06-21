<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class Phase05StoreAndProductTest extends TestCase
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

    public function test_get_dmin_stores_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/stores');
        $response->assertStatus(200);
    }

    public function test_get_dmin_stores_1_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/stores/1');
        $response->assertStatus(404);
    }

    public function test_get_dmin_products_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/products');
        $response->assertStatus(200);
    }

    public function test_get_dmin_categories_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/categories');
        $response->assertStatus(200);
    }

    public function test_get_dmin_brands_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/brands');
        $response->assertStatus(200);
    }


}