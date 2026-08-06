<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanInstallment;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * Phase 2 — Customer Financing Portal demo data (local testing only).
 * Safe to run on SQLite/MySQL; does not commit any database file.
 */
class CustomerPortalSeeder extends Seeder
{
    public function run(): void
    {
        $mobiles = Category::withTrashed()->firstOrCreate(
            ['slug' => 'mobiles'],
            ['name' => 'Mobiles', 'status' => 'active', 'default_down_payment_percent' => 0, 'default_tenure_months' => 6]
        );
        if (method_exists($mobiles, 'trashed') && $mobiles->trashed()) {
            $mobiles->restore();
        }

        $electronics = Category::withTrashed()->firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics', 'status' => 'active', 'default_down_payment_percent' => 0, 'default_tenure_months' => 6]
        );
        if (method_exists($electronics, 'trashed') && $electronics->trashed()) {
            $electronics->restore();
        }

        $laptops = Category::withTrashed()->firstOrCreate(
            ['slug' => 'laptops'],
            ['name' => 'Laptops', 'status' => 'active', 'default_down_payment_percent' => 0, 'default_tenure_months' => 6]
        );
        if (method_exists($laptops, 'trashed') && $laptops->trashed()) {
            $laptops->restore();
        }

        $appliances = Category::withTrashed()->firstOrCreate(
            ['slug' => 'appliances'],
            ['name' => 'Appliances', 'status' => 'active', 'default_down_payment_percent' => 0, 'default_tenure_months' => 6]
        );
        if (method_exists($appliances, 'trashed') && $appliances->trashed()) {
            $appliances->restore();
        }

        $apple = Brand::firstOrCreate(['name' => 'Apple'], ['status' => 'active']);
        $samsung = Brand::firstOrCreate(['name' => 'Samsung'], ['status' => 'active']);
        $sony = Brand::firstOrCreate(['name' => 'Sony'], ['status' => 'active']);
        $lg = Brand::firstOrCreate(['name' => 'LG'], ['status' => 'active']);
        $dyson = Brand::firstOrCreate(['name' => 'Dyson'], ['status' => 'active']);

        $merchant = Merchant::firstOrCreate(
            ['gst_number' => '07AABCU9603R1ZM'],
            [
                'business_name' => 'Croma Connaught Place',
                'pan_number'    => 'AABCU9603R',
                'status'        => 'Approved',
                'region'        => 'North',
                'category'      => 'Electronics',
            ]
        );

        $store = Store::firstOrCreate(
            ['merchant_id' => $merchant->id, 'name' => 'Croma Connaught Place'],
            [
                'address' => 'Connaught Place, New Delhi',
                'status'  => 'active',
            ]
        );

        $catalog = [
            ['sku' => 'P-IPH15PRO', 'name' => 'Apple iPhone 15 Pro (128GB)', 'category' => $mobiles, 'brand' => $apple, 'price' => 134900],
            ['sku' => 'P-SAM55OLED', 'name' => 'Samsung 55" 4K OLED Smart TV', 'category' => $electronics, 'brand' => $samsung, 'price' => 78500],
            ['sku' => 'P-MBAIRM3', 'name' => 'MacBook Air M3 (16GB / 512GB)', 'category' => $laptops, 'brand' => $apple, 'price' => 114900],
            ['sku' => 'P-LG9KG', 'name' => 'LG 9kg AI Front Load Washer', 'category' => $appliances, 'brand' => $lg, 'price' => 42000],
            ['sku' => 'P-PS5SLIM', 'name' => 'Sony PlayStation 5 Slim (1TB)', 'category' => $electronics, 'brand' => $sony, 'price' => 54990],
            ['sku' => 'P-DYSONV15', 'name' => 'Dyson V15 Detect Vacuum', 'category' => $appliances, 'brand' => $dyson, 'price' => 65900],
        ];

        $iphone = null;
        foreach ($catalog as $row) {
            $product = Product::firstOrCreate(
                ['sku' => $row['sku']],
                [
                    'merchant_id'            => $merchant->id,
                    'category_id'            => $row['category']->id,
                    'brand_id'               => $row['brand']->id,
                    'name'                   => $row['name'],
                    'price'                  => $row['price'],
                    'status'                 => 'active',
                    'financing_eligibility'  => true,
                ]
            );
            $store->products()->syncWithoutDetaching([$product->id => ['stock_quantity' => 10]]);
            if ($row['sku'] === 'P-IPH15PRO') {
                $iphone = $product;
            }
        }

        $hdfc = Lender::firstOrCreate(
            ['name' => 'HDFC Bank Ltd'],
            [
                'status'          => 'active',
                'api_status'      => 'live',
                'api_base_url'    => 'https://api.hdfc.example/v1',
                'min_loan_amount' => 5000,
                'max_loan_amount' => 500000,
            ]
        );
        Lender::firstOrCreate(
            ['name' => 'Bajaj Finserv Direct'],
            [
                'status'          => 'active',
                'api_status'      => 'live',
                'api_base_url'    => 'https://api.bajaj.example/v1',
                'min_loan_amount' => 5000,
                'max_loan_amount' => 500000,
            ]
        );
        Lender::firstOrCreate(
            ['name' => 'IDFC First Bank'],
            [
                'status'          => 'active',
                'api_status'      => 'live',
                'api_base_url'    => 'https://api.idfc.example/v1',
                'min_loan_amount' => 5000,
                'max_loan_amount' => 500000,
            ]
        );

        $customer = Customer::updateOrCreate(
            ['phone' => '9876543210'],
            [
                'name'            => 'Rohan Sharma',
                'email'           => 'rohan.sharma@example.com',
                'pan_number'      => 'ABCDE1234F',
                'whatsapp_opt_in' => true,
                'dob'             => '1995-08-15',
                'monthly_income'  => 75000,
                'aadhaar_last4'   => '1098',
                'is_active'       => true,
            ]
        );

        // Demo active loan matching frontend mock LAN-2026-9082
        $loanAmount = 134900;
        $tenure = 6;
        $emi = round($loanAmount / $tenure, 2);
        $paidCount = 3;
        $outstanding = round($emi * ($tenure - $paidCount), 2);

        $application = LoanApplication::firstOrCreate(
            [
                'customer_id' => $customer->id,
                'amount'      => $loanAmount,
                'status'      => 'Disbursed',
            ],
            [
                'merchant_id'   => $merchant->id,
                'store_id'      => $store->id,
                'lender_id'     => $hdfc->id,
                'product_id'    => $iphone?->id,
                'tenure_months' => $tenure,
                'down_payment'  => 0,
                'emi_amount'    => $emi,
                'application_payload' => [
                    'note' => 'Seeded Phase 2 demo application',
                ],
            ]
        );

        $loan = Loan::updateOrCreate(
            ['account_no' => 'LAN-2026-9082'],
            [
                'customer_id'         => $customer->id,
                'loan_application_id' => $application->id,
                'merchant_id'         => $merchant->id,
                'lender_id'           => $hdfc->id,
                'store_id'            => $store->id,
                'product_id'          => $iphone?->id,
                'product_name'        => $iphone?->name ?? 'Apple iPhone 15 Pro (128GB)',
                'loan_amount'         => $loanAmount,
                'outstanding_amount'  => $outstanding,
                'emi_amount'          => $emi,
                'down_payment'        => 0,
                'interest_rate'       => 0,
                'tenure_months'       => $tenure,
                'installments_paid'   => $paidCount,
                'next_due_date'       => '2026-08-05',
                'status'              => 'active',
                'product_category'    => 'Mobiles',
                'approved_at'         => now()->subMonths(3),
                'disbursed_at'        => now()->subMonths(3),
            ]
        );

        $loan->installments()->delete();

        $start = \Carbon\Carbon::parse('2026-05-05');
        for ($i = 1; $i <= $tenure; $i++) {
            $isPaid = $i <= $paidCount;
            LoanInstallment::create([
                'loan_id'        => $loan->id,
                'installment_no' => $i,
                'due_date'       => $start->copy()->addMonths($i - 1)->toDateString(),
                'principal'      => $emi,
                'interest'       => 0,
                'total_emi'      => $emi,
                'status'         => $isPaid ? 'PAID' : ($i === $paidCount + 1 ? 'UPCOMING' : 'UPCOMING'),
                'utr'            => $isPaid ? ('HDFCN' . $start->copy()->addMonths($i - 1)->format('ymd') . rand(1000, 9999)) : null,
                'paid_at'        => $isPaid ? $start->copy()->addMonths($i - 1) : null,
            ]);
        }

        $this->command?->info('Phase 2 customer portal seeded.');
        $this->command?->info('Test phone: 9876543210 | OTP: 123456 | Loan: LAN-2026-9082');
    }
}
