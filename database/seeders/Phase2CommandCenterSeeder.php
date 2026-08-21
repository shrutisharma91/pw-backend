<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Disbursal;
use App\Models\EmiType;
use App\Models\FraudAlert;
use App\Models\Lender;
use App\Models\LoanApplication;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Frozen Super Admin Phase 2 (Command Center) demo data.
 * Numbers are deterministic so presentation output matches this test run.
 */
class Phase2CommandCenterSeeder extends Seeder
{
    public function run(): void
    {
        $salesExec = User::where('email', 'sales.exec@example.com')->first();

        User::updateOrCreate(
            ['email' => 'rajesh.kumar@finz.test'],
            [
                'name' => 'Rajesh Kumar',
                'password' => Hash::make('New@password123'),
                'email_verified_at' => now(),
                'role' => 'superadmin',
                'mobile' => '9876501234',
                'is_active' => true,
            ]
        );

        $tech = Merchant::where('gst_number', '22AAAAA0000A1Z5')->first();
        if ($tech) {
            $tech->update([
                'business_name' => 'Tech Superstore',
                'status' => 'Approved',
                'region' => 'Mumbai',
                'city' => 'Mumbai',
                'tier' => 'Gold',
                'category' => 'Electronics',
                'sales_exec_id' => $salesExec?->id,
            ]);
        }

        $apex = Merchant::updateOrCreate(
            ['gst_number' => '27AAAAA0001A1Z6'],
            [
                'business_name' => 'Apex Mobiles',
                'pan_number' => 'AAAAA0001A',
                'status' => 'Approved',
                'region' => 'Pune',
                'city' => 'Pune',
                'tier' => 'Gold',
                'category' => 'Mobiles',
                'sales_exec_id' => $salesExec?->id,
            ]
        );

        $pending = Merchant::updateOrCreate(
            ['gst_number' => '07BBBBB1111B1Z8'],
            [
                'business_name' => 'Pending Electronics Hub',
                'pan_number' => 'BBBBB1111B',
                'status' => 'Under Review',
                'region' => 'Delhi',
                'city' => 'Delhi',
                'tier' => 'Silver',
                'category' => 'Electronics',
                'sales_exec_id' => $salesExec?->id,
            ]
        );

        $sunrise = Merchant::updateOrCreate(
            ['gst_number' => '29CCCCC2222C1Z3'],
            [
                'business_name' => 'Sunrise Appliances',
                'pan_number' => 'CCCCC2222C',
                'status' => 'Submitted',
                'region' => 'Bengaluru',
                'city' => 'Bengaluru',
                'tier' => 'Bronze',
                'category' => 'Appliances',
                'sales_exec_id' => $salesExec?->id,
            ]
        );

        $techStore = Store::where('name', 'Tech Superstore - Mumbai')->first();

        $apexPune = Store::updateOrCreate(
            ['merchant_id' => $apex->id, 'name' => 'Apex Mobiles - Pune'],
            ['address' => 'FC Road, Pune', 'status' => 'active']
        );

        Store::updateOrCreate(
            ['merchant_id' => $apex->id, 'name' => 'Apex Mobiles - Andheri'],
            ['address' => 'Andheri East, Mumbai', 'status' => 'active']
        );

        $sunriseStore = Store::updateOrCreate(
            ['merchant_id' => $sunrise->id, 'name' => 'Sunrise Appliances - Bengaluru'],
            ['address' => 'Koramangala, Bengaluru', 'status' => 'active']
        );

        $finbank = Lender::firstOrCreate(
            ['name' => 'FinBank Corp'],
            [
                'status' => 'active',
                'api_status' => 'live',
                'api_base_url' => 'https://api.finbank.com/v1',
                'min_loan_amount' => 5000,
                'max_loan_amount' => 500000,
            ]
        );
        $finbank->update(['api_status' => 'live', 'status' => 'active']);

        Lender::updateOrCreate(
            ['name' => 'CreditNow NBFC'],
            [
                'status' => 'active',
                'api_status' => 'live',
                'api_base_url' => 'https://api.creditnow.in/v1',
                'min_loan_amount' => 3000,
                'max_loan_amount' => 300000,
            ]
        );

        $emiType = EmiType::firstOrCreate(
            ['name' => 'Standard No-Cost EMI'],
            [
                'type' => 'no-cost',
                'min_loan_amount' => 5000,
                'max_loan_amount' => 500000,
                'allowed_merchant_tiers' => ['Bronze', 'Silver', 'Gold'],
                'effective_from' => now()->toDateString(),
            ]
        );

        $rahul = Customer::firstOrCreate(
            ['phone' => '9876543210'],
            ['name' => 'Rahul Verma', 'email' => 'rahul@example.com', 'pan_number' => 'ABCDE1234F']
        );

        $merchantForLoans = $apex;
        $storeForLoans = $apexPune;

        $pipeline = [
            ['status' => 'Initiated', 'amount' => 25000, 'sla' => false, 'minutes_ago' => 12],
            ['status' => 'KYC', 'amount' => 42000, 'sla' => false, 'minutes_ago' => 28],
            ['status' => 'Bureau', 'amount' => 58000, 'sla' => true, 'minutes_ago' => 45],
            ['status' => 'Approved', 'amount' => 64000, 'sla' => false, 'minutes_ago' => 90],
            ['status' => 'eSign', 'amount' => 72000, 'sla' => false, 'minutes_ago' => 120],
        ];

        foreach ($pipeline as $row) {
            $loan = LoanApplication::updateOrCreate(
                [
                    'merchant_id' => $merchantForLoans->id,
                    'customer_id' => $rahul->id,
                    'amount' => $row['amount'],
                    'status' => $row['status'],
                ],
                [
                    'store_id' => $storeForLoans->id,
                    'lender_id' => $finbank->id,
                    'emi_type_id' => $emiType->id,
                    'sla_breached' => $row['sla'],
                ]
            );
            $loan->created_at = now()->subMinutes($row['minutes_ago']);
            $loan->updated_at = now()->subMinutes($row['minutes_ago']);
            $loan->save();
        }

        // 7-day disbursal trend (amounts in rupees; dashboard chart divides by 1,00,000)
        $trend = [
            6 => 50000,
            5 => 80000,
            4 => 60000,
            3 => 90000,
            2 => 70000,
            1 => 100000,
            0 => 110000, // today — this is the KPI "Today's Disbursals"
        ];

        foreach ($trend as $daysAgo => $amount) {
            $date = now()->subDays($daysAgo)->setTime(11, 30, 0);
            $loan = LoanApplication::updateOrCreate(
                [
                    'merchant_id' => ($daysAgo % 2 === 0 && $tech) ? $tech->id : $apex->id,
                    'customer_id' => $rahul->id,
                    'amount' => $amount,
                    'status' => 'Disbursed',
                ],
                [
                    'store_id' => ($daysAgo % 2 === 0 && $techStore) ? $techStore->id : $apexPune->id,
                    'lender_id' => $finbank->id,
                    'emi_type_id' => $emiType->id,
                    'sla_breached' => false,
                ]
            );
            $loan->created_at = $date;
            $loan->updated_at = $date;
            $loan->save();

            $disbursal = Disbursal::updateOrCreate(
                ['loan_application_id' => $loan->id],
                [
                    'lender_id' => $finbank->id,
                    'amount' => $amount,
                    'status' => 'Success',
                    'utr_number' => 'UTR' . str_pad((string) (100000 + $daysAgo), 6, '0', STR_PAD_LEFT),
                ]
            );
            $disbursal->created_at = $date;
            $disbursal->updated_at = $date;
            $disbursal->save();
        }

        FraudAlert::updateOrCreate(
            [
                'signal_type' => 'velocity',
                'merchant_id' => $apex->id,
                'severity' => 'High',
            ],
            [
                'customer_id' => $rahul->id,
                'status' => 'Open',
            ]
        );

        Offer::updateOrCreate(
            ['title' => 'Monsoon Cashback Offer'],
            [
                'description' => 'Pending merchant-submitted offer for Command Center testing',
                'offer_type' => 'cashback',
                'discount_value' => 1500,
                'scope_type' => 'platform',
                'status' => 'Pending',
                'merchant_id' => $apex->id,
                'is_platform_offer' => false,
            ]
        );

        // Keep unused locals referenced for readability / future seed expansion.
        unset($pending, $sunriseStore);

        $this->command?->info('Phase 2 Command Center demo data frozen.');
        $this->command?->info('Merchants: ' . Merchant::count());
        $this->command?->info('Active stores: ' . Store::where('status', 'active')->count());
        $this->command?->info('Live lenders: ' . Lender::where('api_status', 'live')->count());
        $this->command?->info("Today's disbursals: " . Disbursal::whereDate('created_at', today())->where('status', 'Success')->sum('amount'));
    }
}
