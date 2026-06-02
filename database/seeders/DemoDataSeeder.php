<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Category
        $cat = \App\Models\Category::withTrashed()->firstOrCreate(
            ['slug' => 'smartphones'],
            [
                'name' => 'Smartphones',
                'status' => 'active',
                'default_down_payment_percent' => 10,
                'default_tenure_months' => 12
            ]
        );
        if ($cat->trashed()) {
            $cat->restore();
        }

        // Brand
        $brand = \App\Models\Brand::firstOrCreate(
            ['name' => 'Apple'],
            ['status' => 'active']
        );

        // Merchant
        $merchant = \App\Models\Merchant::firstOrCreate(
            ['gst_number' => '22AAAAA0000A1Z5'],
            [
                'business_name' => 'Tech Superstore',
                'pan_number' => 'AAAAA0000A',
                'status' => 'Approved',
            ]
        );

        // Store
        $store = \App\Models\Store::firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Tech Superstore - Mumbai'],
            [
                'address' => 'Andheri West, Mumbai',
                'status' => 'active',
            ]
        );

        // Product
        $product = \App\Models\Product::firstOrCreate(
            ['sku' => 'IPH-15-PRO'],
            [
                'name' => 'iPhone 15 Pro',
                'merchant_id' => $merchant->id,
                'category_id' => $cat->id,
                'brand_id' => $brand->id,
                'price' => 120000
            ]
        );

        // Attach Product to Store
        $store->products()->syncWithoutDetaching([$product->id => ['stock_quantity' => 15]]);

        // Lender
        $lender = \App\Models\Lender::firstOrCreate(
            ['name' => 'FinBank Corp'],
            [
                'status' => 'active',
                'api_status' => 'live',
                'api_base_url' => 'https://api.finbank.com/v1',
                'min_loan_amount' => 5000,
                'max_loan_amount' => 500000,
                'api_credentials' => ['key' => 'live_key_123', 'secret' => 'super_secret'],
            ]
        );

        // Merchant Category
        \App\Models\MerchantCategory::firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Electronics']
        );

        // Verification Log
        \App\Models\VerificationLog::firstOrCreate(
            ['merchant_id' => $merchant->id, 'api_type' => 'GST'],
            [
                'status' => 'Failed',
                'provider' => 'Karza',
                'request_payload' => json_encode(['request' => 'verify_gst']),
                'response_payload' => json_encode(['error' => 'API timeout'])
            ]
        );
    }
}
