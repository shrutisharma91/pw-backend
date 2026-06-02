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
        Schema::create('lender_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('conditions'); // JSON logic
            $table->foreignId('lender_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft, active, archived
            $table->integer('version')->default(1);
            $table->integer('ab_test_split')->nullable(); // 0-100
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lender_rules');
    }
};
