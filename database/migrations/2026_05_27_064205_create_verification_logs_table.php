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
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('api_type'); // GST, PAN, Aadhaar, Bank
            $table->string('status'); // Success, Failed, Pending
            $table->string('provider')->nullable(); // Karza, Surepass
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->decimal('cost', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_logs');
    }
};
