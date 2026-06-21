<?php

use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\Lender;
use App\Models\LoanApplication;

use App\Models\EmiType;

$c = Customer::firstOrCreate(['phone' => '1234567890'], ['name' => 'John Doe', 'email' => 'john@example.com', 'pan_number' => 'ABCDE1234F']);
$m = Merchant::firstOrCreate(['business_name' => 'Test Merchant']);
$s = Store::firstOrCreate(['merchant_id' => $m->id], ['name' => 'Test Store']);
$l = Lender::firstOrCreate(['name' => 'Test Lender']);
$e = EmiType::firstOrCreate(['name' => 'Standard EMI'], ['type' => 'no-cost']);

LoanApplication::create([
    'customer_id' => $c->id,
    'merchant_id' => $m->id,
    'store_id' => $s->id,
    'lender_id' => $l->id,
    'amount' => 50000,
    'emi_type_id' => $e->id,
    'status' => 'Pending',
    'sla_breached' => false
]);

LoanApplication::create([
    'customer_id' => $c->id,
    'merchant_id' => $m->id,
    'store_id' => $s->id,
    'lender_id' => $l->id,
    'amount' => 150000,
    'emi_type_id' => $e->id,
    'status' => 'Approved',
    'sla_breached' => true
]);

echo "2 Dummy loan applications created successfully!\n";
