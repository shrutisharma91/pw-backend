<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('lender_id')->nullable()->constrained('lenders')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->decimal('loan_amount', 15, 2)->default(0);
            $table->decimal('outstanding_amount', 15, 2)->default(0);
            $table->decimal('processing_fee_collected', 15, 2)->default(0);
            $table->string('status')->default('initiated');
            $table->string('lender_status')->nullable();
            $table->string('last_stage_reached')->nullable();
            $table->string('product_category')->nullable();
            $table->unsignedSmallInteger('tenure_months')->nullable();
            $table->boolean('is_npa')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['lender_id', 'created_at']);
            $table->index(['merchant_id', 'disbursed_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('interest_component', 15, 2)->default(0);
            $table->decimal('principal_component', 15, 2)->default(0);
            $table->decimal('late_fee', 15, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['paid_at']);
        });

        Schema::create('lender_sla_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->constrained('lenders')->cascadeOnDelete();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('is_breached')->default(false);
            $table->timestamps();

            $table->index(['lender_id', 'created_at']);
        });

        Schema::create('loan_rejection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->nullable()->constrained('lenders')->nullOnDelete();
            $table->string('rejection_reason');
            $table->timestamps();

            $table->index(['lender_id', 'created_at']);
        });

        Schema::create('lender_api_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->constrained('lenders')->cascadeOnDelete();
            $table->unsignedTinyInteger('percentile');
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['lender_id', 'percentile', 'recorded_at']);
        });

        Schema::create('lender_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->nullable()->constrained('lenders')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('subvention_records', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('lenders', function (Blueprint $table) {
            $table->decimal('npa_threshold', 5, 2)->default(5)->after('max_loan_amount');
        });
    }

    public function down(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            $table->dropColumn('npa_threshold');
        });

        Schema::dropIfExists('subvention_records');
        Schema::dropIfExists('lender_commissions');
        Schema::dropIfExists('lender_api_stats');
        Schema::dropIfExists('loan_rejection_logs');
        Schema::dropIfExists('lender_sla_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('loans');
    }
};
