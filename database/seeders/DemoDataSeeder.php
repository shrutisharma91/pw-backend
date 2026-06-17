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

        // Phase 12 — Document Repository demo records
        $admin = \App\Models\User::where('email', 'finzwork10@gmail.com')->first();

        $documents = [
            [
                'storage_path'      => 'documents/demo/merchant-pan.pdf',
                'document_type'     => 'kyc',
                'entity_type'       => 'merchant',
                'entity_id'         => $merchant->id,
                'original_filename' => 'merchant-pan.pdf',
                'file_size_bytes'   => 245760,
                'mime_type'         => 'application/pdf',
                'status'            => 'virus_clean',
                'ocr_status'        => 'done',
                'ocr_text'          => 'PERMANENT ACCOUNT NUMBER AAAAA0000A',
                'virus_scan_status' => 'clean',
                'version'           => 1,
            ],
            [
                'storage_path'      => 'documents/demo/merchant-agreement.pdf',
                'document_type'     => 'agreement',
                'entity_type'       => 'merchant',
                'entity_id'         => $merchant->id,
                'original_filename' => 'merchant-agreement.pdf',
                'file_size_bytes'   => 512000,
                'mime_type'         => 'application/pdf',
                'status'            => 'ocr_done',
                'ocr_status'        => 'done',
                'ocr_text'          => 'MERCHANT ONBOARDING AGREEMENT Tech Superstore',
                'virus_scan_status' => 'clean',
                'version'           => 2,
            ],
            [
                'storage_path'      => 'documents/demo/store-invoice.pdf',
                'document_type'     => 'invoice',
                'entity_type'       => 'store',
                'entity_id'         => $store->id,
                'original_filename' => 'store-invoice-june.pdf',
                'file_size_bytes'   => 128000,
                'mime_type'         => 'application/pdf',
                'status'            => 'pending_ocr',
                'ocr_status'        => 'pending',
                'ocr_text'          => null,
                'virus_scan_status' => 'pending',
                'version'           => 1,
            ],
        ];

        foreach ($documents as $data) {
            $doc = \App\Models\Document::firstOrCreate(
                ['storage_path' => $data['storage_path']],
                array_merge($data, ['uploaded_by' => $admin?->id ?? 1])
            );

            \App\Models\DocumentVersion::firstOrCreate(
                ['document_id' => $doc->id, 'version' => $doc->version],
                [
                    'storage_path'    => $doc->storage_path,
                    'file_size_bytes' => $doc->file_size_bytes,
                    'uploaded_by'     => $admin?->id ?? 1,
                ]
            );
        }
    }
}
