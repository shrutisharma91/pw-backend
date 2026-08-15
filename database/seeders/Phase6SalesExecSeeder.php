<?php

namespace Database\Seeders;

use App\Models\AgentIncentive;
use App\Models\AgentStoreVisit;
use App\Models\AgentTarget;
use App\Models\Customer;
use App\Models\Lender;
use App\Models\LoanApplication;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class Phase6SalesExecSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RbacSeeder::class);

        $period = now()->format('Y-m');
        $lender = Lender::first() ?: Lender::create([
            'name' => 'HDFC Bank Ltd',
            'status' => 'active',
            'api_status' => 'live',
        ]);

        $rahul = User::updateOrCreate(
            ['email' => 'sales.rahul@finz.test'],
            [
                'name' => 'Rahul Mehta',
                'mobile' => '9811100101',
                'password' => Hash::make('password'),
                'role' => 'sales_exec',
                'mfa_verified_at' => now(),
                'mfa_enabled' => true,
                'mfa_channel' => 'email',
                'is_active' => true,
            ]
        );

        $priya = User::updateOrCreate(
            ['email' => 'sales.priya@finz.test'],
            [
                'name' => 'Priya Nair',
                'mobile' => '9811100102',
                'password' => Hash::make('password'),
                'role' => 'sales_exec',
                'mfa_verified_at' => now(),
                'mfa_enabled' => true,
                'mfa_channel' => 'email',
                'is_active' => true,
            ]
        );

        foreach ([$rahul, $priya] as $agent) {
            if (\Spatie\Permission\Models\Role::where('name', 'sales_exec')->exists()) {
                $agent->syncRoles(['sales_exec']);
            }
        }

        AgentTarget::updateOrCreate(
            ['sales_exec_id' => $rahul->id, 'period' => $period],
            ['merchants_onboard_target' => 8, 'disbursal_volume_target' => 2000000]
        );
        AgentTarget::updateOrCreate(
            ['sales_exec_id' => $priya->id, 'period' => $period],
            ['merchants_onboard_target' => 6, 'disbursal_volume_target' => 1500000]
        );

        $vijay = Merchant::updateOrCreate(
            ['gst_number' => '27AAPFU0939F1ZV'],
            [
                'business_name' => 'Vijay Sales Andheri',
                'gst_legal_name' => 'VIJAY SALES INDIA PRIVATE LIMITED',
                'gst_address' => 'S.V. Road, Andheri West, Mumbai, Maharashtra 400058',
                'gst_verified_at' => now()->subDays(20),
                'pan_number' => 'AAPFU0939F',
                'status' => 'Approved',
                'onboarding_stage' => 'approved',
                'category' => 'Electronics',
                'region' => 'West',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400058',
                'address' => 'S.V. Road, Andheri West, Mumbai',
                'sales_exec_id' => $rahul->id,
                'bank_account_number' => '501000112233',
                'bank_ifsc' => 'HDFC0001234',
                'bank_account_name' => 'Vijay Sales India Pvt Ltd',
                'store_facade_url' => 'https://cdn.finz.test/stores/vijay-facade.jpg',
                'store_counter_url' => 'https://cdn.finz.test/stores/vijay-counter.jpg',
                'cheque_url' => 'https://cdn.finz.test/kyc/vijay-cheque.jpg',
                'agreement_esign_status' => 'signed',
                'agreement_esign_at' => now()->subDays(18),
                'tier' => 'Gold',
            ]
        );

        $bright = Merchant::updateOrCreate(
            ['gst_number' => '29AAGCB1234A1Z5'],
            [
                'business_name' => 'Bright Electronics Koramangala',
                'gst_legal_name' => 'BRIGHT ELECTRONICS LLP',
                'gst_address' => '80 Feet Road, Koramangala, Bengaluru, Karnataka 560034',
                'gst_verified_at' => now()->subDays(3),
                'pan_number' => 'AAGCB1234A',
                'status' => 'Submitted',
                'onboarding_stage' => 'docs_collected',
                'category' => 'Electronics',
                'region' => 'South',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'pincode' => '560034',
                'address' => '80 Feet Road, Koramangala, Bengaluru',
                'sales_exec_id' => $rahul->id,
                'bank_account_number' => '029010000445',
                'bank_ifsc' => 'ICIC0000290',
                'bank_account_name' => 'Bright Electronics LLP',
                'store_facade_url' => 'https://cdn.finz.test/stores/bright-facade.jpg',
                'store_counter_url' => 'https://cdn.finz.test/stores/bright-counter.jpg',
                'cheque_url' => 'https://cdn.finz.test/kyc/bright-cheque.jpg',
                'agreement_esign_status' => 'initiated',
                'agreement_esign_at' => now()->subDay(),
            ]
        );

        $hub = Merchant::updateOrCreate(
            ['gst_number' => '27AAACM1234B1Z2'],
            [
                'business_name' => 'Mobile Hub Bandra',
                'gst_legal_name' => 'FIELD ONBOARDED MERCHANT AAACM1234B',
                'gst_verified_at' => now()->subDays(1),
                'pan_number' => 'AAACM1234B',
                'status' => 'Draft',
                'onboarding_stage' => 'lead',
                'category' => 'Mobiles',
                'region' => 'West',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400050',
                'address' => 'Linking Road, Bandra West, Mumbai',
                'sales_exec_id' => $rahul->id,
            ]
        );

        $gadget = Merchant::updateOrCreate(
            ['gst_number' => '24AAACR5055K1Z8'],
            [
                'business_name' => 'Gadget World Ahmedabad',
                'gst_legal_name' => 'GADGET WORLD RETAIL PRIVATE LIMITED',
                'gst_verified_at' => now()->subDays(8),
                'pan_number' => 'AAACR5055K',
                'status' => 'Under Review',
                'onboarding_stage' => 'inspection_done',
                'category' => 'Electronics',
                'region' => 'West',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'pincode' => '380009',
                'address' => 'CG Road, Navrangpura, Ahmedabad',
                'sales_exec_id' => $rahul->id,
                'bank_account_number' => '918020012345',
                'bank_ifsc' => 'UTIB0000123',
                'bank_account_name' => 'Gadget World Retail Pvt Ltd',
                'store_facade_url' => 'https://cdn.finz.test/stores/gadget-facade.jpg',
                'store_counter_url' => 'https://cdn.finz.test/stores/gadget-counter.jpg',
                'cheque_url' => 'https://cdn.finz.test/kyc/gadget-cheque.jpg',
                'agreement_esign_status' => 'signed',
                'agreement_esign_at' => now()->subDays(6),
            ]
        );

        $priyaShop = Merchant::updateOrCreate(
            ['gst_number' => '33AAACP9999C1Z1'],
            [
                'business_name' => 'Chennai Digital Mart',
                'status' => 'Approved',
                'onboarding_stage' => 'approved',
                'category' => 'Electronics',
                'region' => 'South',
                'city' => 'Chennai',
                'state' => 'Tamil Nadu',
                'sales_exec_id' => $priya->id,
                'pan_number' => 'AAACP9999C',
            ]
        );

        $vijayStore = Store::updateOrCreate(
            ['merchant_id' => $vijay->id, 'name' => 'Vijay Sales Andheri West'],
            [
                'address' => 'S.V. Road, Andheri West, Mumbai',
                'latitude' => 19.1197,
                'longitude' => 72.8468,
                'status' => 'active',
                'manager_name' => 'Amit Roy',
            ]
        );

        $brightStore = Store::updateOrCreate(
            ['merchant_id' => $bright->id, 'name' => 'Bright Electronics Koramangala'],
            [
                'address' => '80 Feet Road, Koramangala, Bengaluru',
                'latitude' => 12.9352,
                'longitude' => 77.6245,
                'status' => 'active',
            ]
        );

        $gadgetStore = Store::updateOrCreate(
            ['merchant_id' => $gadget->id, 'name' => 'Gadget World CG Road'],
            [
                'address' => 'CG Road, Navrangpura, Ahmedabad',
                'latitude' => 23.0330,
                'longitude' => 72.5660,
                'status' => 'active',
            ]
        );

        Store::updateOrCreate(
            ['merchant_id' => $priyaShop->id, 'name' => 'Chennai Digital Mart T Nagar'],
            [
                'address' => 'T Nagar, Chennai',
                'latitude' => 13.0418,
                'longitude' => 80.2337,
                'status' => 'active',
            ]
        );

        foreach ([$vijay, $bright, $gadget, $hub, $priyaShop] as $merchant) {
            $merchant->store_count = $merchant->stores()->count();
            $merchant->save();
        }

        $product = Product::firstOrCreate(
            ['sku' => 'IPH-15-PRO-FIELD'],
            [
                'name' => 'iPhone 15 Pro',
                'merchant_id' => $vijay->id,
                'price' => 134900,
                'status' => 'active',
                'financing_eligibility' => true,
            ]
        );

        $customer = Customer::firstOrCreate(
            ['phone' => '9822001100'],
            ['name' => 'Ananya Iyer', 'is_active' => true, 'pan_number' => 'AAAPI1111A']
        );

        $loanA = LoanApplication::updateOrCreate(
            ['customer_id' => $customer->id, 'store_id' => $vijayStore->id, 'amount' => 89900],
            [
                'merchant_id' => $vijay->id,
                'lender_id' => $lender->id,
                'sales_exec_id' => $rahul->id,
                'product_id' => $product->id,
                'tenure_months' => 9,
                'down_payment' => 45000,
                'emi_amount' => 9988.89,
                'status' => 'Disbursed',
                'application_payload' => [
                    'application_number' => 'FLD-SEED0001',
                    'origin' => 'sales_exec_assisted',
                ],
            ]
        );

        $loanB = LoanApplication::updateOrCreate(
            ['customer_id' => $customer->id, 'store_id' => $vijayStore->id, 'amount' => 54990],
            [
                'merchant_id' => $vijay->id,
                'lender_id' => $lender->id,
                'sales_exec_id' => $rahul->id,
                'product_id' => $product->id,
                'tenure_months' => 6,
                'status' => 'Disbursed',
                'application_payload' => [
                    'application_number' => 'FLD-SEED0002',
                    'origin' => 'sales_exec_assisted',
                ],
            ]
        );

        AgentStoreVisit::updateOrCreate(
            ['sales_exec_id' => $rahul->id, 'store_id' => $vijayStore->id],
            [
                'merchant_id' => $vijay->id,
                'latitude' => 19.1198,
                'longitude' => 72.8469,
                'checked_in_at' => now()->subDays(2),
                'qr_standee_placed' => true,
                'staff_trained' => true,
                'pos_active' => true,
                'merchant_active' => true,
                'notes' => 'QR standee reprinted. Staff trained on counter checkout.',
            ]
        );

        AgentStoreVisit::updateOrCreate(
            ['sales_exec_id' => $rahul->id, 'store_id' => $gadgetStore->id],
            [
                'merchant_id' => $gadget->id,
                'latitude' => 23.0331,
                'longitude' => 72.5661,
                'checked_in_at' => now()->subDays(1),
                'qr_standee_placed' => true,
                'staff_trained' => true,
                'pos_active' => true,
                'merchant_active' => true,
                'notes' => 'Inspection complete. Ready for Super Admin approval.',
            ]
        );

        AgentIncentive::updateOrCreate(
            ['sales_exec_id' => $rahul->id, 'loan_application_id' => $loanA->id],
            [
                'merchant_id' => $vijay->id,
                'period' => $period,
                'loan_amount' => $loanA->amount,
                'commission_rate_pct' => 0.75,
                'commission_amount' => round(((float) $loanA->amount) * 0.0075, 2),
                'payout_status' => 'paid',
                'tier' => 'Gold',
                'paid_at' => now()->subDays(3),
            ]
        );

        AgentIncentive::updateOrCreate(
            ['sales_exec_id' => $rahul->id, 'loan_application_id' => $loanB->id],
            [
                'merchant_id' => $vijay->id,
                'period' => $period,
                'loan_amount' => $loanB->amount,
                'commission_rate_pct' => 0.75,
                'commission_amount' => round(((float) $loanB->amount) * 0.0075, 2),
                'payout_status' => 'pending',
                'tier' => 'Gold',
            ]
        );

        $this->command?->info('Phase 6 sales executive field portal seeded.');
        $this->command?->info('Login: sales.rahul@finz.test / password / MFA 123456');
        $this->command?->info('Isolation user: sales.priya@finz.test / password / MFA 123456');
    }
}
