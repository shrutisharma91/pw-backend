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
        Schema::create('settlement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lender_id')->constrained('lenders');
            $table->date('date');
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->decimal('total_fees', 12, 2)->default(0);
            $table->decimal('total_net', 12, 2)->default(0);
            $table->string('utr_number')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_batches');
    }
};
