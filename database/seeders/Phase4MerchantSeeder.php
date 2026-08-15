<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Offer;
use Illuminate\Support\Facades\DB;

class Phase4MerchantSeeder extends Seeder
{
    public function run(): void
    {
        $merchant = Merchant::firstOrCreate(
            ['business_name' => 'Croma Retail'],
            [
                'status' => 'active',
                'gst_legal_name' => 'Infiniti Retail Ltd',
                'city' => 'Mumbai',
                'state' => 'MH',
                'tier' => 'Gold'
            ]
        );

        User::updateOrCreate(
            ['email' => 'merchant_mfa@croma.test'],
            [
                'name' => 'Croma CEO (MFA Enabled)',
                'password' => Hash::make('password123'),
                'role' => 'merchant_admin',
                'merchant_id' => $merchant->id,
                'is_active' => true,
                'mfa_enabled' => true,
                'mfa_channel' => 'email',
            ]
        );

        $storeManager = User::updateOrCreate(
            ['email' => 'store_mgr@croma.test'],
            [
                'name' => 'Croma Store Manager',
                'password' => Hash::make('password123'),
                'role' => 'store_manager',
                'merchant_id' => $merchant->id,
                'is_active' => true,
            ]
        );

        $store = Store::firstOrCreate(
            ['store_code' => 'CR-ANDHERI-01'],
            [
                'merchant_id' => $merchant->id,
                'name' => 'Croma Andheri West',
                'city' => 'Mumbai',
                'region' => 'West',
                'pin_code' => '400053',
                'manager_id' => $storeManager->id,
                'manager_name' => 'Croma Store Manager',
                'status' => 'active',
            ]
        );
        
        $storeManager->update(['store_id' => $store->id]);

        $cashier = User::updateOrCreate(
            ['email' => 'cashier@croma.test'],
            [
                'name' => 'Croma Cashier',
                'password' => Hash::make('password123'),
                'role' => 'cashier',
                'merchant_id' => $merchant->id,
                'store_id' => $store->id,
                'is_active' => true,
            ]
        );

        DB::table('pos_terminals')->insertOrIgnore([
            'store_id' => $store->id,
            'terminal_id' => 'TERM-001',
            'status' => 'active',
            'assigned_cashier_id' => $cashier->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('subvention_matrices')->insertOrIgnore([
            'merchant_id' => $merchant->id,
            'tenure_months' => 6,
            'subvention_percentage' => 4.50,
            'merchant_split' => 100.00,
            'lender_split' => 0.00,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $category = Category::firstOrCreate(['name' => 'Smartphones', 'status' => 'active']);
        $brand = Brand::firstOrCreate(['name' => 'Apple', 'status' => 'active']);

        $product = Product::firstOrCreate(
            ['sku' => 'IP15-PRO-128'],
            [
                'merchant_id' => $merchant->id,
                'name' => 'iPhone 15 Pro 128GB',
                'price' => 134900,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'financing_eligibility' => true,
                'status' => 'active',
            ]
        );

        $store->products()->syncWithoutDetaching([$product->id => ['stock_quantity' => 15]]);

        Offer::firstOrCreate(
            ['title' => 'Diwali Smartphone Bonanza'],
            [
                'merchant_id' => $merchant->id,
                'description' => '0% EMI on all Apple Smartphones',
                'offer_type' => 'percentage',
                'discount_value' => 10.00,
                'scope_type' => 'category',
                'scope_value' => json_encode([$category->id]),
                'start_date' => now(),
                'end_date' => now()->addDays(30),
                'status' => 'Active',
                'approval_status' => 'Approved',
                'target_categories' => json_encode([$category->id]),
                'target_stores' => json_encode([$store->id]),
                'min_cart_value' => 50000,
                'budget_cap' => 1000000,
            ]
        );
    }
}
