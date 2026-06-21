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
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_type'); // velocity, scoring
            $table->string('name');
            $table->json('parameters');
            $table->decimal('threshold', 8, 2);
            $table->string('action'); // flag, hold, reject
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_rules');
    }
};
