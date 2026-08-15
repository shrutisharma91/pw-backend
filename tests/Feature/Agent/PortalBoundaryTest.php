<?php

namespace Tests\Feature\Agent;

use App\Models\AdminSession;
use App\Models\Lender;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PortalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        $payload = JWTAuth::setToken($token)->getPayload();

        AdminSession::create([
            'user_id' => $user->id,
            'token_id' => $payload->get('jti'),
            'ip_address' => '127.0.0.1',
            'device_info' => 'PHPUnit',
            'device_type' => 'desktop',
            'is_active' => true,
            'logged_in_at' => now(),
        ]);

        return ['Authorization' => 'Bearer ' . $token];
    }

    private function salesExec(string $email = 'sales.rahul@test.local'): User
    {
        return User::create([
            'name' => 'Sales Exec',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'sales_exec',
            'mfa_verified_at' => now(),
            'mfa_enabled' => true,
            'is_active' => true,
        ]);
    }

    private function merchantFor(User $agent, string $gstin, string $name = 'Field Shop'): array
    {
        $merchant = Merchant::create([
            'business_name' => $name,
            'gst_number' => $gstin,
            'status' => 'Approved',
            'onboarding_stage' => 'approved',
            'pan_number' => 'ABCDE1234F',
            'sales_exec_id' => $agent->id,
        ]);
        $store = Store::create([
            'merchant_id' => $merchant->id,
            'name' => $name . ' Store',
            'status' => 'active',
            'address' => 'Mumbai',
            'latitude' => 19.12,
            'longitude' => 72.84,
        ]);

        return [$merchant, $store];
    }

    public function test_sales_exec_can_load_dashboard(): void
    {
        $agent = $this->salesExec();

        $this->getJson('/api/v1/agent/dashboard/metrics', $this->bearer($agent))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['kpis', 'pipeline', 'target']]);
    }

    public function test_lender_ops_cannot_access_agent_api(): void
    {
        $user = User::create([
            'name' => 'Lender',
            'email' => 'lender@test.local',
            'password' => Hash::make('password'),
            'role' => 'lender_ops',
            'mfa_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/agent/dashboard/metrics', $this->bearer($user))
            ->assertStatus(403)
            ->assertJsonPath('code', 'agent_api_role_denied');
    }

    public function test_super_admin_requires_sales_exec_id(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@test.local',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'mfa_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/agent/dashboard/metrics', $this->bearer($admin))
            ->assertStatus(422)
            ->assertJsonPath('code', 'sales_exec_id_required');
    }

    public function test_agent_cannot_see_another_agents_merchant(): void
    {
        $rahul = $this->salesExec('rahul@test.local');
        $priya = $this->salesExec('priya@test.local');
        [$merchant] = $this->merchantFor($rahul, '27AAPFU0939F1ZV', 'Rahul Shop');

        $this->getJson('/api/v1/agent/merchants/' . $merchant->id, $this->bearer($priya))
            ->assertStatus(404);
    }

    public function test_verify_gstin_returns_lookup(): void
    {
        $agent = $this->salesExec();

        $this->postJson('/api/v1/agent/merchant/verify-gstin', [
            'gstin' => '27AAPFU0939F1ZV',
        ], $this->bearer($agent))
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'VIJAY SALES INDIA PRIVATE LIMITED')
            ->assertJsonPath('data.already_onboarded', false);
    }

    public function test_onboard_creates_merchant_for_agent(): void
    {
        $agent = $this->salesExec();

        $this->postJson('/api/v1/agent/merchant/onboard', [
            'gstin' => '29AAGCB1234A1Z5',
            'store_facade_url' => 'https://cdn.example/facade.jpg',
            'bank_account_number' => '1234567890',
            'bank_ifsc' => 'HDFC0001111',
            'bank_account_name' => 'Bright Electronics LLP',
            'store' => [
                'name' => 'Bright Koramangala',
                'latitude' => 12.9352,
                'longitude' => 77.6245,
            ],
        ], $this->bearer($agent))
            ->assertCreated()
            ->assertJsonPath('data.merchant.sales_exec_id', $agent->id)
            ->assertJsonPath('data.merchant.onboarding_stage', 'docs_collected');
    }

    public function test_gps_checkin_and_incentive_statement(): void
    {
        $agent = $this->salesExec();
        [, $store] = $this->merchantFor($agent, '24AAACR5055K1Z8', 'Gadget World');

        $checkin = $this->postJson('/api/v1/agent/audits/checkin', [
            'store_id' => $store->id,
            'latitude' => 23.0330,
            'longitude' => 72.5660,
            'qr_standee_placed' => true,
            'staff_trained' => true,
            'pos_active' => true,
            'merchant_active' => true,
            'notes' => 'All good',
        ], $this->bearer($agent));

        $checkin->assertCreated()->assertJsonPath('data.checklist_complete', true);

        $this->getJson('/api/v1/agent/incentives/statement', $this->bearer($agent))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['kpis', 'tier', 'breakdown']]);
    }

    public function test_assisted_checkout_submits_underwriting_loan(): void
    {
        $agent = $this->salesExec();
        [$merchant, $store] = $this->merchantFor($agent, '07AABCU9603R1ZX', 'Croma CP');
        $lender = Lender::create(['name' => 'HDFC Test', 'status' => 'active']);
        $product = Product::create([
            'merchant_id' => $merchant->id,
            'name' => 'iPhone 15 Pro',
            'sku' => 'IPH-FIELD-1',
            'price' => 134900,
            'status' => 'active',
            'financing_eligibility' => true,
        ]);

        $headers = $this->bearer($agent);

        $this->postJson('/api/v1/agent/checkout/send-otp', [
            'phone' => '9876500001',
            'aadhaar' => '123412341234',
            'merchant_id' => $merchant->id,
            'store_id' => $store->id,
        ], $headers)->assertOk()->assertJsonPath('dev_otp', '123456');

        $verify = $this->postJson('/api/v1/agent/checkout/verify-otp', [
            'phone' => '9876500001',
            'otp' => '123456',
        ], $headers)->assertOk();

        $customerId = $verify->json('data.customer_id');

        $this->postJson('/api/v1/agent/checkout/submit', [
            'customer_id' => $customerId,
            'merchant_id' => $merchant->id,
            'store_id' => $store->id,
            'product_id' => $product->id,
            'amount' => 80000,
            'down_payment' => 10000,
            'tenure' => 6,
            'lender_id' => $lender->id,
            'selfie_url' => 'https://cdn.example/selfie.jpg',
            'pan_url' => 'https://cdn.example/pan.jpg',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.status', 'Underwriting');
    }

    public function test_agent_cannot_checkin_at_another_agents_store(): void
    {
        $rahul = $this->salesExec('rahul2@test.local');
        $priya = $this->salesExec('priya2@test.local');
        [, $store] = $this->merchantFor($rahul, '33AAACP9999C1Z1', 'Rahul Only');

        $this->postJson('/api/v1/agent/audits/checkin', [
            'store_id' => $store->id,
            'latitude' => 13.04,
            'longitude' => 80.23,
        ], $this->bearer($priya))
            ->assertStatus(404);
    }
}
