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
        Schema::create('tenure_slabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_type_id')->constrained()->cascadeOnDelete();
            $table->integer('tenure_months');
            $table->decimal('base_interest_rate', 5, 2)->default(0);
            $table->enum('processing_fee_type', ['flat', 'percentage'])->default('flat');
            $table->decimal('processing_fee_value', 10, 2)->default(0);
            $table->decimal('processing_fee_cap', 10, 2)->nullable();
            $table->json('tier_overrides')->nullable(); // overrides for gold, silver, bronze
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenure_slabs');
    }
};
