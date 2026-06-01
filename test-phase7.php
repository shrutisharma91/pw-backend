<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$emi = App\Models\EmiType::create([
    'name' => 'Test EMI',
    'type' => 'no-cost',
    'min_loan_amount' => 1000,
    'allowed_merchant_tiers' => ['Gold', 'Silver']
]);
echo "Created EMI Type ID: " . $emi->id . "\n";

$slab = App\Models\TenureSlab::create([
    'emi_type_id' => $emi->id,
    'tenure_months' => 6,
    'base_interest_rate' => 0,
    'processing_fee_type' => 'flat',
    'processing_fee_value' => 500,
    'tier_overrides' => ['Gold' => ['processing_fee_value' => 0]]
]);
echo "Created Slab ID: " . $slab->id . "\n";

$offer = App\Models\Offer::create([
    'title' => 'Diwali Dhamaka',
    'offer_type' => 'flat',
    'discount_value' => 500,
    'scope_type' => 'platform',
    'budget_cap' => 100000,
    'is_platform_offer' => true
]);
echo "Created Offer ID: " . $offer->id . "\n";
