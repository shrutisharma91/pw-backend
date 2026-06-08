<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class BlacklistTest extends TestCase
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

    public function test_get_blacklist_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/blacklist');
        $response->assertStatus(200);
    }

    public function test_post_blacklist_1()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/blacklist');
        $response->assertStatus(422);
    }

    public function test_post_blacklist_bulk_import_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/blacklist/bulk-import');
        $response->assertStatus(200);
    }

    public function test_post_blacklist_1_remove_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/blacklist/1/remove');
        $response->assertStatus(422);
    }

    public function test_post_blacklist_1_whitelist_override_4()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/blacklist/1/whitelist-override');
        $response->assertStatus(422);
    }


}