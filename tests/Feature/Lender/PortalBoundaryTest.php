<?php

namespace Tests\Feature\Lender;

use App\Models\AdminSession;
use App\Models\Customer;
use App\Models\Lender;
use App\Models\LoanApplication;
use App\Models\Merchant;
use App\Models\SettlementBatch;
use App\Models\Store;
use App\Models\User;
use App\Support\RbacCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

    private function syncLenderOpsRole(User $user, bool $withDisbursalAuthorizer = true): void
    {
        $guard = RbacCatalog::guard();
        $role = Role::findOrCreate('lender_ops', $guard);

        foreach (RbacCatalog::modules()['LenderPortal'] ?? [] as $perm) {
            Permission::findOrCreate($perm, $guard);
        }

        $perms = Permission::where('guard_name', $guard)
            ->whereIn('name', RbacCatalog::modules()['LenderPortal'] ?? [])
            ->get();

        if (!$withDisbursalAuthorizer) {
            $perms = $perms->reject(fn ($p) => $p->name === 'disbursal_authorizer');
        }

        $role->syncPermissions($perms);
        $user->syncRoles([$role]);
    }

    private function lenderUser(Lender $lender, string $email, bool $withDisbursalAuthorizer = true): User
    {
        $user = User::create([
            'name' => 'Lender Ops',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'lender_ops',
            'lender_id' => $lender->id,
            'mfa_verified_at' => now(),
            'mfa_enabled' => true,
            'is_active' => true,
        ]);

        $this->syncLenderOpsRole($user, $withDisbursalAuthorizer);

        return $user;
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'super@test.local',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'mfa_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function seedApplication(Lender $lender, array $overrides = []): LoanApplication
    {
        $merchant = Merchant::create([
            'business_name' => 'Test Merchant',
            'gst_number' => 'GST' . uniqid(),
            'status' => 'Approved',
            'pan_number' => 'ABCDE1234F',
        ]);
        $store = Store::create([
            'merchant_id' => $merchant->id,
            'name' => 'Store 1',
            'status' => 'active',
            'address' => 'Addr',
        ]);

        $customer = Customer::create([
            'phone' => '9' . random_int(100000000, 999999999),
            'name' => 'Borrower',
            'is_active' => true,
        ]);

        return LoanApplication::create(array_merge([
            'customer_id' => $customer->id,
            'merchant_id' => $merchant->id,
            'store_id' => $store->id,
            'lender_id' => $lender->id,
            'amount' => 50000,
            'status' => 'Underwriting',
            'tenure_months' => 6,
            'application_payload' => ['cibil' => 720],
        ], $overrides));
    }

    public function test_lender_ops_cannot_access_admin_merchants(): void
    {
        $lender = Lender::create(['name' => 'Lender A', 'status' => 'active']);
        $user = $this->lenderUser($lender, 'lender.a@test.local');

        $this->getJson('/api/v1/admin/merchants', $this->bearer($user))->assertStatus(403);
    }

    public function test_lender_a_cannot_see_lender_b_application_in_queue(): void
    {
        $lenderA = Lender::create(['name' => 'Lender A', 'status' => 'active']);
        $lenderB = Lender::create(['name' => 'Lender B', 'status' => 'active']);
        $userA = $this->lenderUser($lenderA, 'lender.a2@test.local');

        $appB = $this->seedApplication($lenderB);
        $this->seedApplication($lenderA);

        $response = $this->getJson('/api/v1/lender/applications/queue', $this->bearer($userA));
        $response->assertStatus(200);
        $this->assertFalse(collect($response->json('data'))->pluck('id')->contains($appB->id));
    }

    public function test_lender_a_gets_404_for_lender_b_application_detail(): void
    {
        $lenderA = Lender::create(['name' => 'Lender A', 'status' => 'active']);
        $lenderB = Lender::create(['name' => 'Lender B', 'status' => 'active']);
        $userA = $this->lenderUser($lenderA, 'lender.a3@test.local');
        $appB = $this->seedApplication($lenderB);

        $this->getJson("/api/v1/lender/applications/{$appB->id}", $this->bearer($userA))->assertStatus(404);
    }

    public function test_cross_lender_decision_returns_404(): void
    {
        $lenderA = Lender::create(['name' => 'Lender A', 'status' => 'active']);
        $lenderB = Lender::create(['name' => 'Lender B', 'status' => 'active']);
        $userA = $this->lenderUser($lenderA, 'lender.a4@test.local');
        $appB = $this->seedApplication($lenderB);

        $this->postJson(
            "/api/v1/lender/applications/{$appB->id}/decision",
            ['decision' => 'approve', 'reason' => 'ok'],
            $this->bearer($userA)
        )->assertStatus(404);
    }

    public function test_super_admin_requires_lender_id_on_lender_api(): void
    {
        $admin = $this->superAdmin();
        Lender::create(['name' => 'Lender A', 'status' => 'active']);

        $this->getJson('/api/v1/lender/dashboard/metrics', $this->bearer($admin))->assertStatus(422);
    }

    public function test_super_admin_can_view_lender_with_query_param(): void
    {
        $admin = $this->superAdmin();
        $lender = Lender::create(['name' => 'Lender A', 'status' => 'active']);
        $this->seedApplication($lender);

        $this->getJson(
            '/api/v1/lender/dashboard/metrics?lender_id=' . $lender->id,
            $this->bearer($admin)
        )->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_disbursal_release_requires_authorizer_permission(): void
    {
        $lender = Lender::create(['name' => 'Lender A', 'status' => 'active']);
        $user = $this->lenderUser($lender, 'lender.noauth@test.local', false);

        $batch = SettlementBatch::create([
            'lender_id' => $lender->id,
            'date' => now()->toDateString(),
            'total_gross' => 1000,
            'total_fees' => 10,
            'total_net' => 990,
            'status' => 'Pending',
        ]);

        $this->postJson(
            "/api/v1/lender/disbursals/batches/{$batch->id}/release",
            [],
            $this->bearer($user)
        )->assertStatus(403)->assertJsonPath('code', 'dual_signature_required');
    }

    public function test_decision_endpoint_approves_own_application(): void
    {
        $lender = Lender::create(['name' => 'Lender A', 'status' => 'active']);
        $user = $this->lenderUser($lender, 'lender.decide@test.local');
        $app = $this->seedApplication($lender);

        $this->postJson(
            "/api/v1/lender/applications/{$app->id}/decision",
            ['decision' => 'approve', 'reason' => 'Good file', 'approved_amount' => 45000],
            $this->bearer($user)
        )->assertStatus(200)->assertJsonPath('data.status', 'Approved');
    }
}
