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
        Schema::create('emi_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['no-cost', 'interest-bearing', 'deferred']);
            $table->decimal('min_loan_amount', 15, 2)->default(0);
            $table->decimal('max_loan_amount', 15, 2)->nullable();
            $table->json('allowed_merchant_tiers')->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emi_types');
    }
};
