<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class LoanApplicationTest extends TestCase
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

    public function test_get_loans_0()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans');
        $response->assertStatus(200);
    }

    public function test_get_loans_export_1()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans/export');
        $response->assertStatus(200);
    }

    public function test_get_loans_saved_filters_2()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans/saved-filters');
        $response->assertStatus(200);
    }

    public function test_post_loans_saved_filters_3()
    {
        $response = $this->actingAs($this->user)->post('/api/v1/admin/loans/saved-filters');
        $response->assertStatus(422);
    }

    public function test_get_loans_1_4()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans/1');
        $response->assertStatus(404);
    }

    public function test_get_loans_1_timeline_5()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans/1/timeline');
        $response->assertStatus(200);
    }

    public function test_get_loans_1_documents_6()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans/1/documents');
        $response->assertStatus(200);
    }

    public function test_get_loans_1_communications_7()
    {
        $response = $this->actingAs($this->user)->get('/api/v1/admin/loans/1/communications');
        $response->assertStatus(200);
    }


}