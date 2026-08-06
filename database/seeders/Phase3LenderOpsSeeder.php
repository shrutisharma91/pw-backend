<?php

namespace Database\Seeders;

use App\Models\BounceEvent;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Disbursal;
use App\Models\Lender;
use App\Models\LenderRule;
use App\Models\LoanApplication;
use App\Models\LoanCommunication;
use App\Models\LoanDocument;
use App\Models\LoanTimelineEvent;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\SettlementBatch;
use App\Models\SettlementEntry;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class Phase3LenderOpsSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::firstOrCreate(
            ['gst_number' => '27ABCDE1234F1Z5'],
            ['business_name' => 'Croma CP', 'status' => 'Approved', 'pan_number' => 'ABCDE1234F']
        );
        $store = Store::firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Croma Connaught Place'],
            ['status' => 'active', 'address' => 'Connaught Place, New Delhi']
        );

        $customerA = Customer::firstOrCreate(['phone' => '9899000001'], ['name' => 'Rohan Sharma', 'is_active' => true]);
        $customerB = Customer::firstOrCreate(['phone' => '9899000002'], ['name' => 'Sneha Reddy', 'is_active' => true]);
        $customerC = Customer::firstOrCreate(['phone' => '9899000003'], ['name' => 'Manish Verma', 'is_active' => true]);

        $hdfc = Lender::firstOrCreate(
            ['name' => 'HDFC Bank Ltd'],
            ['status' => 'active', 'api_status' => 'live', 'api_base_url' => 'https://api.hdfc.example/v1']
        );
        $bajaj = Lender::firstOrCreate(
            ['name' => 'Bajaj Finserv Direct'],
            ['status' => 'active', 'api_status' => 'live', 'api_base_url' => 'https://api.bajaj.example/v1']
        );

        User::updateOrCreate(
            ['email' => 'lender.hdfc@finz.test'],
            [
                'name' => 'HDFC Lender Ops',
                'mobile' => '9899001001',
                'password' => Hash::make('password'),
                'role' => 'lender_ops',
                'lender_id' => $hdfc->id,
                'mfa_verified_at' => now(),
                'mfa_enabled' => true,
                'mfa_channel' => 'email',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'lender.bajaj@finz.test'],
            [
                'name' => 'Bajaj Lender Ops',
                'mobile' => '9899001002',
                'password' => Hash::make('password'),
                'role' => 'lender_ops',
                'lender_id' => $bajaj->id,
                'mfa_verified_at' => now(),
                'mfa_enabled' => true,
                'mfa_channel' => 'email',
                'is_active' => true,
            ]
        );

        $this->call(RbacSeeder::class);
        foreach (['lender.hdfc@finz.test', 'lender.bajaj@finz.test'] as $email) {
            $ops = User::where('email', $email)->first();
            if ($ops && \Spatie\Permission\Models\Role::where('name', 'lender_ops')->exists()) {
                $ops->syncRoles(['lender_ops']);
            }
        }

        $product = Product::first() ?: Product::create([
            'merchant_id' => $merchant->id,
            'name' => 'iPhone 15 Pro',
            'sku' => 'IPH15PRO-LENDER',
            'price' => 134900,
            'status' => 'active',
            'financing_eligibility' => true,
        ]);

        $apps = [
            [
                'customer' => $customerA,
                'lender' => $hdfc,
                'amount' => 134900,
                'status' => 'Underwriting',
                'payload' => ['cibil' => 762, 'monthly_income' => 75000, 'dti' => '28%', 'flag_reason' => 'Income mismatch >15%', 'bureau_status' => 'Clean'],
            ],
            [
                'customer' => $customerB,
                'lender' => $hdfc,
                'amount' => 78000,
                'status' => 'Initiated',
                'payload' => ['cibil' => 694, 'monthly_income' => 56000, 'dti' => '43%', 'flag_reason' => 'Recent bureau queries', 'bureau_status' => 'Watchlist'],
            ],
            [
                'customer' => $customerC,
                'lender' => $bajaj,
                'amount' => 88000,
                'status' => 'Approved',
                'payload' => ['cibil' => 710, 'monthly_income' => 68000, 'dti' => '34%', 'flag_reason' => 'High FOIR edge case', 'bureau_status' => 'Clean'],
            ],
        ];

        $createdApps = [];
        foreach ($apps as $row) {
            $app = LoanApplication::updateOrCreate(
                [
                    'customer_id' => $row['customer']->id,
                    'lender_id' => $row['lender']->id,
                    'amount' => $row['amount'],
                ],
                [
                    'merchant_id' => $merchant->id,
                    'store_id' => $store->id,
                    'product_id' => $product->id,
                    'tenure_months' => 6,
                    'status' => $row['status'],
                    'sla_breached' => $row['status'] === 'Underwriting',
                    'application_payload' => $row['payload'],
                ]
            );
            $createdApps[] = $app;

            LoanTimelineEvent::firstOrCreate(
                ['loan_application_id' => $app->id, 'event_name' => 'Application Received'],
                ['stage' => 'Initiated', 'payload' => ['source' => 'seed']]
            );
            LoanTimelineEvent::firstOrCreate(
                ['loan_application_id' => $app->id, 'event_name' => 'Bureau Check Completed'],
                ['stage' => 'Underwriting', 'payload' => ['result' => 'ok']]
            );

            LoanDocument::firstOrCreate(
                ['loan_application_id' => $app->id, 'document_type' => 'KFS'],
                ['file_path' => 'documents/demo/kfs-' . $app->id . '.pdf', 'status' => 'Verified']
            );
            LoanCommunication::firstOrCreate(
                ['loan_application_id' => $app->id, 'type' => 'SMS', 'content' => 'Your application is under process'],
                ['status' => 'Sent']
            );
        }

        foreach ($createdApps as $app) {
            if (in_array($app->status, ['Underwriting', 'Initiated'], true)) {
                \App\Models\ManualReview::firstOrCreate(
                    ['loan_application_id' => $app->id],
                    [
                        'risk_score' => (float) ($app->application_payload['cibil'] ?? 700),
                        'status' => 'Pending',
                        'sla_deadline' => now()->addMinutes(20),
                        'sla_breached' => $app->status === 'Underwriting',
                    ]
                );
            }
        }

        foreach ($createdApps as $app) {
            if (in_array($app->status, ['Approved', 'Underwriting'], true)) {
                Disbursal::firstOrCreate(
                    ['loan_application_id' => $app->id],
                    ['lender_id' => $app->lender_id, 'amount' => $app->amount, 'status' => 'Pending']
                );
            }
        }

        $batch = SettlementBatch::firstOrCreate(
            ['lender_id' => $hdfc->id, 'date' => now()->toDateString()],
            ['total_gross' => 212900, 'total_fees' => 4200, 'total_net' => 208700, 'status' => 'Pending']
        );

        foreach ($createdApps as $app) {
            if ($app->lender_id === $hdfc->id) {
                SettlementEntry::firstOrCreate(
                    ['settlement_batch_id' => $batch->id, 'loan_application_id' => $app->id],
                    [
                        'merchant_id' => $merchant->id,
                        'gross' => $app->amount,
                        'fees' => round($app->amount * 0.02, 2),
                        'net' => round($app->amount * 0.98, 2),
                        'status' => 'matched',
                    ]
                );
            }
        }

        foreach ($createdApps as $app) {
            Collection::firstOrCreate(
                ['loan_application_id' => $app->id],
                [
                    'dpd_bucket' => $app->status === 'Approved' ? '31-60' : '0-30',
                    'overdue_amount' => round($app->amount * 0.08, 2),
                    'status' => 'Pending',
                    'npa_status' => null,
                ]
            );
        }

        $collection = Collection::first();
        if ($collection) {
            BounceEvent::firstOrCreate(
                ['collection_id' => $collection->id, 'date' => now()->toDateString()],
                ['amount' => $collection->overdue_amount, 'auto_retry_status' => 'Pending']
            );
        }

        LenderRule::firstOrCreate(
            ['name' => 'Minimum CIBIL Score Threshold', 'lender_id' => $hdfc->id],
            ['conditions' => ['min_cibil' => 700, 'max_foir' => 50, 'income_threshold' => 25000], 'status' => 'active', 'version' => 1]
        );
        LenderRule::firstOrCreate(
            ['name' => 'Maximum DTI', 'lender_id' => $hdfc->id],
            ['conditions' => ['min_cibil' => 680, 'max_foir' => 55, 'income_threshold' => 20000], 'status' => 'draft', 'version' => 1]
        );

        $this->command?->info('Phase 3 lender ops demo data seeded.');
    }
}
