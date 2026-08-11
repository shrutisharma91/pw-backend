<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;
use App\Models\Merchant;
use App\Models\Lender;
use App\Models\Product;
use App\Models\Customer;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Phase5PosSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::first();
        $store = Store::first();
        $lender = Lender::first();
        $product = Product::first();

        if (!$merchant || !$store || !$lender || !$product) {
            $this->command->warn('Required Phase 4 data is missing. Run DemoDataSeeder first.');
            return;
        }

        // 1. Create Cashiers
        for ($i = 1; $i <= 3; $i++) {
            User::firstOrCreate(
                ['email' => "cashier{$i}@techsuperstore.com"],
                [
                    'name' => "Store Cashier {$i}",
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                    'role' => 'cashier',
                    'merchant_id' => $merchant->id,
                    'store_ids' => [(string)$store->id],
                    'is_active' => true,
                ]
            );
        }

        // 2. Create Customers
        $customers = [];
        for ($i = 1; $i <= 5; $i++) {
            $customers[] = Customer::firstOrCreate(
                ['phone' => "987654321{$i}"],
                [
                    'name' => "Test Customer {$i}",
                    'pan_number' => "ABCDE123{$i}F",
                    'dob' => '1990-01-01',
                    'email' => "customer{$i}@example.com",
                    'is_active' => true,
                ]
            );
        }

        // 3. Create Loan Applications
        $statuses = ['Initiated', 'Bureau', 'Approved', 'Disbursed', 'Rejected'];
        
        foreach ($customers as $index => $customer) {
            LoanApplication::firstOrCreate(
                ['customer_id' => $customer->id, 'store_id' => $store->id],
                [
                    'merchant_id' => $merchant->id,
                    'lender_id' => $lender->id,
                    'amount' => $product->price,
                    'emi_type_id' => null,
                    'status' => $statuses[$index],
                    'sla_breached' => false,
                ]
            );
        }

        $this->command->info('Phase 5 POS Data Seeded Successfully!');
    }
}
