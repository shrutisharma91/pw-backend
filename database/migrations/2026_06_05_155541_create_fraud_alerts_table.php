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
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('signal_type'); // device, IP, geo, velocity, document
            $table->string('severity'); // High, Medium, Low
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('merchant_id')->nullable()->constrained('merchants');
            $table->string('status')->default('Open'); // Open, Blocked, Escalated, Resolved
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_alerts');
    }
};
