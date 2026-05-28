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
        Schema::create('lender_waterfalls', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('priority_order'); // JSON array of lender IDs
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('region')->nullable();
            $table->time('time_window_start')->nullable();
            $table->time('time_window_end')->nullable();
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lender_waterfalls');
    }
};
