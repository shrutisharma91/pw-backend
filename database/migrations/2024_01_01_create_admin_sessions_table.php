<?php
// =================================================================
// MIGRATION 2: Create admin_sessions table
// File: database/migrations/2024_01_01_000002_create_admin_sessions_table.php
// =================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token_id')->unique(); // JWT jti claim — unique per token
            $table->string('ip_address', 45);     // IPv4 or IPv6
            $table->text('device_info')->nullable();
            $table->string('device_type')->default('desktop'); // mobile/tablet/desktop
            $table->string('location')->nullable(); // e.g., "Mumbai, India"
            $table->boolean('is_active')->default(true);
            $table->boolean('is_suspicious')->default(false);
            $table->string('suspicious_reason')->nullable();
            $table->timestamp('logged_in_at');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_active']); // fast lookup for active sessions
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sessions');
    }
};
