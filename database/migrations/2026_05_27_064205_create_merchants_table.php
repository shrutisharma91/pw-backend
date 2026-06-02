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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('status')->default('Draft'); // Draft, Submitted, Under Review, Approved, Rejected, Re-KYC, Suspended
            $table->string('region')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('sales_exec_id')->nullable()->constrained('users')->nullOnDelete();
            
            // KYC Info
            $table->string('gst_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('aadhaar_number')->nullable();
            $table->string('shop_license')->nullable();
            
            // Metrics
            $table->integer('store_count')->default(0);
            $table->decimal('disbursal_volume', 15, 2)->default(0);
            $table->decimal('npa', 5, 2)->default(0); // non-performing asset %
            
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
