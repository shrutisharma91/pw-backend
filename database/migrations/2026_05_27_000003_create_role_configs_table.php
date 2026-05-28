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
        Schema::create('role_configs', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique();
            $table->json('allowlist')->nullable(); // Default: []
            $table->json('denylist')->nullable();  // Default: []
            $table->integer('concurrent_session_limit')->default(5);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_configs');
    }
};
