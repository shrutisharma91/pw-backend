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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('offer_type', ['flat', 'percentage', 'cashback', 'coupon']);
            $table->decimal('discount_value', 10, 2);
            $table->enum('scope_type', ['platform', 'merchant_tier', 'category', 'lender', 'tenure', 'geo']);
            $table->string('scope_value')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('recurrence')->nullable();
            $table->json('blackout_dates')->nullable();
            $table->decimal('budget_cap', 15, 2)->nullable();
            $table->decimal('budget_utilized', 15, 2)->default(0);
            $table->boolean('auto_pause')->default(true);
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Active', 'Paused', 'Expired'])->default('Pending');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->cascadeOnDelete();
            $table->text('approval_reason')->nullable();
            $table->boolean('is_platform_offer')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
