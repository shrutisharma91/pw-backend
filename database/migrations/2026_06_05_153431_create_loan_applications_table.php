<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            // Nullable until Phase 2 wizard completes (merchant/store/lender selected)
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('lender_id')->nullable()->constrained('lenders')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->foreignId('emi_type_id')->nullable()->constrained('emi_types')->nullOnDelete();
            $table->string('status')->default('Initiated'); // Initiated, KYC, Bureau, Approved, eSign, eNACH, Disbursed, Rejected, Cancelled
            $table->boolean('sla_breached')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
