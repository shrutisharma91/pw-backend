<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class ManualReviewTest extends TestCase
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

    public function test_get_ual_reviews_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/manual-reviews');
        $response->assertStatus(200);
    }

    public function test_get_ual_reviews_1_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/manual-reviews/1');
        $response->assertStatus(404);
    }

    public function test_post_ual_reviews_1_decide_2()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/manual-reviews/1/decide');
        $response->assertStatus(422);
    }

    public function test_get_ual_reviews_scorecard_1_3()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/manual-reviews/scorecard/1');
        $response->assertStatus(200);
    }


}