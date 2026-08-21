<?php

namespace Database\Seeders;

use App\Models\Lender;
use App\Models\Loan;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Frozen Phase 11 analytics numbers (loans table).
 * Super Admin dashboard KPIs still use loan_applications + disbursals from Phase 2.
 */
class Phase11AnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        $apex = Merchant::where('business_name', 'Apex Mobiles')->first();
        $tech = Merchant::where('business_name', 'Tech Superstore')->first();
        $finbank = Lender::where('name', 'FinBank Corp')->first();
        $creditNow = Lender::where('name', 'CreditNow NBFC')->first();
        $apexStore = Store::where('name', 'Apex Mobiles - Pune')->first();
        $techStore = Store::where('name', 'Tech Superstore - Mumbai')->first();

        if (! $apex || ! $finbank) {
            $this->command?->warn('Phase 11 skipped — merchants/lenders missing.');
            return;
        }

        $rows = [
            // 7-day disbursal trend (matches Phase 2 Command Center amounts)
            ['days' => 6, 'amount' => 50000, 'merchant' => $tech ?? $apex, 'store' => $techStore ?? $apexStore, 'lender' => $finbank, 'category' => 'Electronics', 'tenure' => 12, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 5, 'amount' => 80000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 9, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 4, 'amount' => 60000, 'merchant' => $tech ?? $apex, 'store' => $techStore ?? $apexStore, 'lender' => $creditNow ?? $finbank, 'category' => 'Electronics', 'tenure' => 6, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 3, 'amount' => 90000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 12, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 2, 'amount' => 70000, 'merchant' => $tech ?? $apex, 'store' => $techStore ?? $apexStore, 'lender' => $finbank, 'category' => 'Electronics', 'tenure' => 9, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 1, 'amount' => 100000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $creditNow ?? $finbank, 'category' => 'Mobiles', 'tenure' => 12, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 0, 'amount' => 110000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 12, 'npa' => false, 'status' => 'disbursed'],
            // Rest of 30d window
            ['days' => 10, 'amount' => 45000, 'merchant' => $tech ?? $apex, 'store' => $techStore ?? $apexStore, 'lender' => $finbank, 'category' => 'Electronics', 'tenure' => 6, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 14, 'amount' => 35000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $creditNow ?? $finbank, 'category' => 'Mobiles', 'tenure' => 9, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 20, 'amount' => 55000, 'merchant' => $tech ?? $apex, 'store' => $techStore ?? $apexStore, 'lender' => $finbank, 'category' => 'Electronics', 'tenure' => 12, 'npa' => false, 'status' => 'disbursed'],
            ['days' => 22, 'amount' => 80000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 12, 'npa' => true, 'status' => 'disbursed'],
            // Funnel stages still in-flight
            ['days' => 1, 'amount' => 25000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 6, 'npa' => false, 'status' => 'initiated'],
            ['days' => 2, 'amount' => 42000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 9, 'npa' => false, 'status' => 'kyc_submitted'],
            ['days' => 3, 'amount' => 58000, 'merchant' => $tech ?? $apex, 'store' => $techStore ?? $apexStore, 'lender' => $finbank, 'category' => 'Electronics', 'tenure' => 12, 'npa' => false, 'status' => 'bureau_checked'],
            ['days' => 4, 'amount' => 64000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 12, 'npa' => false, 'status' => 'approved'],
            ['days' => 5, 'amount' => 72000, 'merchant' => $apex, 'store' => $apexStore, 'lender' => $finbank, 'category' => 'Mobiles', 'tenure' => 9, 'npa' => false, 'status' => 'esign_done'],
            // Last-year YoY comparison (same calendar month last year)
            ['days' => 370, 'amount' => 400000, 'merchant' => $tech ?? $apex, 'store' => $techStore ?? $apexStore, 'lender' => $finbank, 'category' => 'Electronics', 'tenure' => 12, 'npa' => false, 'status' => 'disbursed'],
        ];

        foreach ($rows as $row) {
            $at = now()->subDays($row['days'])->setTime(11, 30, 0);
            $isDisbursed = $row['status'] === 'disbursed';

            $loan = Loan::updateOrCreate(
                [
                    'merchant_id' => $row['merchant']->id,
                    'loan_amount' => $row['amount'],
                    'status' => $row['status'],
                    'product_category' => $row['category'],
                    'is_npa' => $row['npa'],
                ],
                [
                    'lender_id' => $row['lender']->id,
                    'store_id' => $row['store']?->id,
                    'outstanding_amount' => $isDisbursed ? ($row['npa'] ? $row['amount'] * 0.6 : $row['amount'] * 0.4) : $row['amount'],
                    'processing_fee_collected' => $isDisbursed ? round($row['amount'] * 0.02, 2) : 0,
                    'lender_status' => in_array($row['status'], ['approved', 'esign_done', 'enach_done', 'disbursed'], true) ? 'approved' : ($row['status'] === 'initiated' ? 'pending' : 'in_review'),
                    'last_stage_reached' => $row['status'],
                    'tenure_months' => $row['tenure'],
                    'is_npa' => $row['npa'],
                    'approved_at' => $isDisbursed || $row['status'] === 'approved' ? $at->copy()->subHours(6) : null,
                    'disbursed_at' => $isDisbursed ? $at : null,
                ]
            );
            $loan->created_at = $at;
            $loan->updated_at = $at;
            $loan->save();

            if ($isDisbursed && ! $loan->payments()->exists()) {
                Payment::create([
                    'loan_id' => $loan->id,
                    'amount' => round($row['amount'] / max(1, $row['tenure']), 2),
                    'interest_component' => round($row['amount'] * 0.012, 2),
                    'principal_component' => round($row['amount'] / max(1, $row['tenure']) * 0.8, 2),
                    'late_fee' => $row['npa'] ? 500 : 0,
                    'paid_at' => $at->copy()->addDays(5),
                ]);
            }
        }

        if ($finbank && ! DB::table('lender_commissions')->where('lender_id', $finbank->id)->exists()) {
            DB::table('lender_commissions')->insert([
                'lender_id' => $finbank->id,
                'amount' => 12500,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]);
        }

        if (! DB::table('subvention_records')->exists()) {
            DB::table('subvention_records')->insert([
                'amount' => 8000,
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(4),
            ]);
        }

        if ($finbank && ! DB::table('loan_rejection_logs')->where('lender_id', $finbank->id)->exists()) {
            DB::table('loan_rejection_logs')->insert([
                ['lender_id' => $finbank->id, 'rejection_reason' => 'Low bureau score', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)],
                ['lender_id' => $finbank->id, 'rejection_reason' => 'Incomplete KYC', 'created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)],
                ['lender_id' => $creditNow?->id ?? $finbank->id, 'rejection_reason' => 'Income mismatch', 'created_at' => now()->subDays(6), 'updated_at' => now()->subDays(6)],
            ]);
        }

        if ($finbank && ! DB::table('lender_sla_logs')->where('lender_id', $finbank->id)->exists()) {
            DB::table('lender_sla_logs')->insert([
                ['lender_id' => $finbank->id, 'response_time_ms' => 420, 'is_breached' => false, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)],
                ['lender_id' => $finbank->id, 'response_time_ms' => 1800, 'is_breached' => true, 'created_at' => now()->subDays(1), 'updated_at' => now()->subDays(1)],
                ['lender_id' => $creditNow?->id ?? $finbank->id, 'response_time_ms' => 310, 'is_breached' => false, 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)],
            ]);
        }

        if ($finbank && ! DB::table('lender_api_stats')->where('lender_id', $finbank->id)->exists()) {
            DB::table('lender_api_stats')->insert([
                ['lender_id' => $finbank->id, 'percentile' => 50, 'latency_ms' => 280, 'recorded_at' => now()->subDay()],
                ['lender_id' => $finbank->id, 'percentile' => 95, 'latency_ms' => 910, 'recorded_at' => now()->subDay()],
            ]);
        }

        $this->command?->info('Phase 11 analytics loans: ' . Loan::count());
        $this->command?->info('30d disbursed volume: ' . Loan::where('status', 'disbursed')->where('disbursed_at', '>=', now()->subDays(30))->sum('loan_amount'));
    }
}
