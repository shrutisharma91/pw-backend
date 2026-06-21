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
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants');
            $table->string('consent_type'); // KFS, terms, data_sharing, marketing
            $table->string('version');
            $table->json('payload');
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->string('status')->default('Active'); // Active, Withdrawn
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
