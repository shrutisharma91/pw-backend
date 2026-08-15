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
        Schema::create('subvention_matrices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('lender_id')->nullable(); // Nullable if applies to all lenders for this merchant
            $table->integer('tenure_months');
            $table->decimal('subvention_percentage', 5, 2);
            $table->decimal('merchant_split', 5, 2)->default(100.00); // Percentage of the subvention paid by merchant
            $table->decimal('lender_split', 5, 2)->default(0.00);    // Percentage paid by lender
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->json('category_overrides')->nullable();
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->foreign('lender_id')->references('id')->on('lenders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subvention_matrices');
    }
};
