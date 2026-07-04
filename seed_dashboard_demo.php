<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\Disbursal;
use App\Models\EmiType;
use App\Models\FraudAlert;
use App\Models\Lender;
use App\Models\LoanApplication;
use App\Models\Merchant;
use App\Models\Offer;
use App\Models\Store;

$merchant = Merchant::first();
$store = Store::where('merchant_id', $merchant?->id)->first();
$lender = Lender::first();

if (!$merchant || !$store || !$lender) {
    echo "Run DemoDataSeeder first (merchant/store/lender missing).\n";
    exit(1);
}

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

Merchant::firstOrCreate(
    ['business_name' => 'Pending Electronics Hub'],
    [
        'gst_number' => '27BBBBB1111B1Z8',
        'pan_number' => 'BBBBB1111B',
        'status' => 'Under Review',
        'region' => 'Delhi',
        'tier' => 'Silver',
    ]
);

$customer = Customer::firstOrCreate(
    ['phone' => '9876543210'],
    ['name' => 'Rahul Verma', 'email' => 'rahul@example.com', 'pan_number' => 'ABCDE1234F']
);

$statuses = ['Initiated', 'KYC', 'Bureau', 'Approved', 'Disbursed'];
foreach ($statuses as $index => $status) {
    $loan = LoanApplication::firstOrCreate(
        [
            'merchant_id' => $merchant->id,
            'customer_id' => $customer->id,
            'amount' => 50000 + ($index * 15000),
            'status' => $status,
        ],
        [
            'store_id' => $store->id,
            'lender_id' => $lender->id,
            'emi_type_id' => $emiType->id,
            'sla_breached' => $status === 'Bureau',
        ]
    );

    if ($status === 'Disbursed') {
        Disbursal::firstOrCreate(
            ['loan_application_id' => $loan->id, 'status' => 'Success'],
            [
                'lender_id' => $lender->id,
                'amount' => $loan->amount,
                'utr_number' => 'UTR' . str_pad((string) $loan->id, 6, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}

FraudAlert::firstOrCreate(
    ['signal_type' => 'velocity', 'merchant_id' => $merchant->id, 'severity' => 'High'],
    ['customer_id' => $customer->id, 'status' => 'Open']
);

Offer::firstOrCreate(
    ['title' => 'Monsoon Cashback Offer'],
    [
        'description' => 'Pending merchant-submitted offer for dashboard testing',
        'offer_type' => 'cashback',
        'discount_value' => 1500,
        'scope_type' => 'platform',
        'status' => 'Pending',
        'merchant_id' => $merchant->id,
        'is_platform_offer' => false,
    ]
);

echo "Dashboard demo data ready.\n";
echo 'merchants=' . Merchant::count() . PHP_EOL;
echo 'stores=' . Store::where('status', 'active')->count() . PHP_EOL;
echo 'lenders_live=' . Lender::where('api_status', 'live')->count() . PHP_EOL;
echo 'loans=' . LoanApplication::count() . PHP_EOL;
echo 'todays_disbursals=' . Disbursal::whereDate('created_at', today())->where('status', 'Success')->sum('amount') . PHP_EOL;
