<?php
// =================================================================
// MIGRATION 3: Create admin_notifications table
// File: database/migrations/2024_01_01_000003_create_admin_notifications_table.php
// =================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            // Type maps to tabs in Screen 05
            $table->enum('type', ['approval', 'alert', 'system', 'mention', 'info'])
                  ->default('info');

            // Priority for colour coding in Screen 05
            $table->enum('priority', ['critical', 'high', 'medium', 'info'])
                  ->default('info');

            $table->string('title');
            $table->text('message');
            $table->string('action_url')->nullable();  // where to go on click
            $table->string('action_label')->nullable(); // button text

            $table->json('data')->nullable(); // any extra payload

            $table->boolean('is_read')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'is_read', 'is_archived']); // fast for notification list
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
