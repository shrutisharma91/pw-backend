<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 50);
            $table->string('provider_key', 100);
            $table->string('base_url')->nullable();
            $table->text('api_key_enc')->nullable();
            $table->text('api_secret_enc')->nullable();
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_fallback')->default(false);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->unsignedSmallInteger('timeout_seconds')->default(30);
            $table->unsignedTinyInteger('retry_attempts')->default(2);
            $table->timestamp('credential_rotation_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['category', 'provider_key']);
            $table->index(['category', 'is_active']);
        });

        Schema::create('integration_call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_id');
            $table->string('endpoint')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->boolean('is_success')->default(true);
            $table->string('error_code')->nullable();
            $table->decimal('cost', 10, 4)->default(0);
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();

            $table->foreign('integration_id')->references('id')->on('integrations')->onDelete('cascade');
            $table->index(['integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_call_logs');
        Schema::dropIfExists('integrations');
    }
};