<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Sales Executive Field Portal (Screens 43–47)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            if (!Schema::hasColumn('merchants', 'onboarding_stage')) {
                $table->string('onboarding_stage')->default('lead')->after('status');
            }
            if (!Schema::hasColumn('merchants', 'gst_legal_name')) {
                $table->string('gst_legal_name')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'gst_address')) {
                $table->text('gst_address')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'gst_verified_at')) {
                $table->timestamp('gst_verified_at')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'address')) {
                $table->text('address')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'state')) {
                $table->string('state')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'pincode')) {
                $table->string('pincode', 10)->nullable();
            }
            if (!Schema::hasColumn('merchants', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'bank_ifsc')) {
                $table->string('bank_ifsc', 20)->nullable();
            }
            if (!Schema::hasColumn('merchants', 'bank_account_name')) {
                $table->string('bank_account_name')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'store_facade_url')) {
                $table->string('store_facade_url')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'store_counter_url')) {
                $table->string('store_counter_url')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'agreement_esign_status')) {
                $table->string('agreement_esign_status')->nullable();
            }
            if (!Schema::hasColumn('merchants', 'agreement_esign_at')) {
                $table->timestamp('agreement_esign_at')->nullable();
            }
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_applications', 'sales_exec_id')) {
                $table->foreignId('sales_exec_id')->nullable()->after('lender_id')->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('agent_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_exec_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->unsignedInteger('merchants_onboard_target')->default(8);
            $table->decimal('disbursal_volume_target', 15, 2)->default(2000000);
            $table->timestamps();
            $table->unique(['sales_exec_id', 'period']);
        });

        Schema::create('agent_store_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_exec_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('checked_in_at');
            $table->boolean('qr_standee_placed')->default(false);
            $table->boolean('staff_trained')->default(false);
            $table->boolean('pos_active')->default(false);
            $table->boolean('merchant_active')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->timestamps();

            $table->index(['sales_exec_id', 'checked_in_at']);
            $table->index(['merchant_id', 'store_id']);
        });

        Schema::create('agent_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_exec_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('loan_application_id')->nullable()->constrained('loan_applications')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->decimal('loan_amount', 15, 2)->default(0);
            $table->decimal('commission_rate_pct', 5, 2)->default(0);
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->string('payout_status')->default('pending'); // pending, paid
            $table->string('tier')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['sales_exec_id', 'period']);
            $table->index(['sales_exec_id', 'payout_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_incentives');
        Schema::dropIfExists('agent_store_visits');
        Schema::dropIfExists('agent_targets');

        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'sales_exec_id')) {
                $table->dropConstrainedForeignId('sales_exec_id');
            }
        });

        Schema::table('merchants', function (Blueprint $table) {
            $cols = [
                'onboarding_stage', 'gst_legal_name', 'gst_address', 'gst_verified_at',
                'address', 'city', 'state', 'pincode',
                'bank_account_number', 'bank_ifsc', 'bank_account_name',
                'store_facade_url', 'store_counter_url',
                'agreement_esign_status', 'agreement_esign_at',
            ];
            $existing = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('merchants', $c)));
            if ($existing) {
                $table->dropColumn($existing);
            }
        });
    }
};
