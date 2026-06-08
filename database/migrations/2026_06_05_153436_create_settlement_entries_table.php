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
        Schema::create('settlement_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_batch_id')->constrained('settlement_batches');
            $table->foreignId('merchant_id')->constrained('merchants');
            $table->foreignId('loan_application_id')->constrained('loan_applications');
            $table->decimal('gross', 10, 2);
            $table->decimal('fees', 10, 2);
            $table->decimal('net', 10, 2);
            $table->string('status')->default('matched'); // matched, unmatched, disputed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_entries');
    }
};
