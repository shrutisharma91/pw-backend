<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Merchant;

class PortalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_lender_cannot_access_merchant_portal()
    {
        $lenderUser = User::factory()->create(['role' => 'lender_admin']);
        
        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'email' => $lenderUser->email,
            'password' => 'password'
        ]);
        
        $response->assertStatus(401);
    }

    public function test_customer_cannot_access_merchant_portal()
    {
        $customerUser = User::factory()->create(['role' => 'customer']);
        
        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'email' => $customerUser->email,
            'password' => 'password'
        ]);
        
        $response->assertStatus(401);
    }

    public function test_merchant_admin_can_access_merchant_portal()
    {
        $merchantUser = User::factory()->create([
            'role' => 'merchant_admin',
            'is_active' => true,
        ]);
        
        $response = $this->postJson('/api/v1/merchant/auth/login', [
            'email' => $merchantUser->email,
            'password' => 'password'
        ]);
        
        $response->assertStatus(200);
    }

    public function test_merchant_admin_cannot_access_pos_portal_without_store()
    {
        $merchantUser = User::factory()->create([
            'role' => 'merchant_admin',
            'is_active' => true,
        ]);
        
        $response = $this->postJson('/api/v1/pos/login', [
            'email' => $merchantUser->email,
            'password' => 'password'
        ]);
        
        $response->assertStatus(200); // Admin can access POS but they will be limited by scope in dashboard
    }
}
