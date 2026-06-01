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
        $cat = \App\Models\Category::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'status' => 'active',
            'default_down_payment_percent' => 10,
            'default_tenure_months' => 12
        ]);

        // Brand
        $brand = \App\Models\Brand::create([
            'name' => 'Apple',
            'status' => 'active',
        ]);

        // Merchant
        $merchant = \App\Models\Merchant::create([
            'business_name' => 'Tech Superstore',
            'gst_number' => '22AAAAA0000A1Z5',
            'pan_number' => 'AAAAA0000A',
            'status' => 'Approved',
        ]);

        // Store
        $store = \App\Models\Store::create([
            'merchant_id' => $merchant->id,
            'name' => 'Tech Superstore - Mumbai',
            'address' => 'Andheri West, Mumbai',
            'status' => 'active',
        ]);

        // Product
        $product = \App\Models\Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IPH-15-PRO',
            'merchant_id' => $merchant->id,
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'price' => 120000
        ]);

        // Attach Product to Store
        $store->products()->attach($product->id, ['stock_quantity' => 15]);

        // Lender
        $lender = \App\Models\Lender::create([
            'name' => 'FinBank Corp',
            'status' => 'active',
            'api_status' => 'live',
            'api_base_url' => 'https://api.finbank.com/v1',
            'min_loan_amount' => 5000,
            'max_loan_amount' => 500000,
            'api_credentials' => ['key' => 'live_key_123', 'secret' => 'super_secret'],
        ]);

        // Merchant Category
        \App\Models\MerchantCategory::create([
            'merchant_id' => $merchant->id,
            'name' => 'Electronics',
            'status' => 'Pending'
        ]);

        // Verification Log
        \App\Models\VerificationLog::create([
            'merchant_id' => $merchant->id,
            'document_type' => 'GST Certificate',
            'status' => 'Failed',
            'api_payload' => json_encode(['request' => 'verify_gst']),
            'api_response' => json_encode(['error' => 'API timeout']),
            'error_message' => 'Upstream provider timeout'
        ]);
    }
}
