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
            $table->foreignId('merchant_id')->constrained('merchants');
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('lender_id')->constrained('lenders');
            $table->decimal('amount', 10, 2);
            $table->foreignId('emi_type_id')->constrained('emi_types');
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
