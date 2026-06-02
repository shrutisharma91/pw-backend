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
        Schema::create('lenders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo_url')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->string('api_status')->default('live'); // live, degraded, down
            $table->string('api_base_url')->nullable();
            $table->text('api_credentials')->nullable(); // Encrypted JSON
            $table->json('webhook_endpoints')->nullable();
            $table->json('supported_tenures')->nullable();
            $table->decimal('min_loan_amount', 12, 2)->nullable();
            $table->decimal('max_loan_amount', 12, 2)->nullable();
            $table->json('commission_structure')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lenders');
    }
};
