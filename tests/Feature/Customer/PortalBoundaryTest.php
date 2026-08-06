<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\Merchant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PortalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function customerToken(Customer $customer): array
    {
        $token = auth('customer')->login($customer);

        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_customer_token_cannot_access_admin_merchants(): void
    {
        $customer = Customer::create([
            'name' => 'Rohan',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        // Even with a bearer token, admin APIs require auth:api user + MFA session.
        $response = $this->getJson('/api/v1/admin/merchants', $this->customerToken($customer));
        $this->assertTrue(in_array($response->status(), [401, 403], true));
    }

    public function test_customer_a_cannot_see_customer_b_loan(): void
    {
        $customerA = Customer::create(['name' => 'A', 'phone' => '9000000001', 'is_active' => true]);
        $customerB = Customer::create(['name' => 'B', 'phone' => '9000000002', 'is_active' => true]);

        $merchant = Merchant::create([
            'business_name' => 'Shop',
            'gst_number' => 'GST' . uniqid(),
            'status' => 'Approved',
            'pan_number' => 'ABCDE1234F',
        ]);
        $lender = Lender::create(['name' => 'HDFC', 'status' => 'active']);

        $loanB = Loan::create([
            'customer_id' => $customerB->id,
            'merchant_id' => $merchant->id,
            'lender_id' => $lender->id,
            'account_no' => 'LN-B-1',
            'loan_amount' => 10000,
            'outstanding_amount' => 8000,
            'emi_amount' => 2000,
            'tenure_months' => 6,
            'status' => 'active',
            'product_name' => 'Phone',
        ]);

        Loan::create([
            'customer_id' => $customerA->id,
            'merchant_id' => $merchant->id,
            'lender_id' => $lender->id,
            'account_no' => 'LN-A-1',
            'loan_amount' => 12000,
            'outstanding_amount' => 9000,
            'emi_amount' => 2000,
            'tenure_months' => 6,
            'status' => 'active',
            'product_name' => 'Phone',
        ]);

        $this->getJson(
            "/api/v1/customer/loans/{$loanB->id}/schedule",
            $this->customerToken($customerA)
        )->assertStatus(404);
    }

    public function test_customer_sees_only_own_active_loans(): void
    {
        $customerA = Customer::create(['name' => 'A', 'phone' => '9000000011', 'is_active' => true]);
        $customerB = Customer::create(['name' => 'B', 'phone' => '9000000012', 'is_active' => true]);
        $merchant = Merchant::create([
            'business_name' => 'Shop',
            'gst_number' => 'GST' . uniqid(),
            'status' => 'Approved',
            'pan_number' => 'ABCDE1234F',
        ]);
        $lender = Lender::create(['name' => 'HDFC', 'status' => 'active']);

        Loan::create([
            'customer_id' => $customerA->id,
            'merchant_id' => $merchant->id,
            'lender_id' => $lender->id,
            'account_no' => 'LN-A-2',
            'loan_amount' => 12000,
            'outstanding_amount' => 9000,
            'emi_amount' => 2000,
            'tenure_months' => 6,
            'status' => 'active',
            'product_name' => 'Phone',
        ]);
        Loan::create([
            'customer_id' => $customerB->id,
            'merchant_id' => $merchant->id,
            'lender_id' => $lender->id,
            'account_no' => 'LN-B-2',
            'loan_amount' => 15000,
            'outstanding_amount' => 10000,
            'emi_amount' => 2500,
            'tenure_months' => 6,
            'status' => 'active',
            'product_name' => 'TV',
        ]);

        $response = $this->getJson('/api/v1/customer/loans/active', $this->customerToken($customerA));
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('account_no');
        $this->assertTrue($ids->contains('LN-A-2'));
        $this->assertFalse($ids->contains('LN-B-2'));
    }
}
