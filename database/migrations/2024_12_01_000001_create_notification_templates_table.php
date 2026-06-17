<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('template_key', 100)->unique();
            $table->enum('channel', ['sms', 'email', 'whatsapp', 'push']);
            $table->string('subject')->nullable();
            $table->json('variables')->nullable();
            $table->string('sender_id', 50)->nullable();
            $table->string('dlt_template_id', 100)->nullable();
            $table->string('language', 10)->default('en');
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->unsignedSmallInteger('current_version')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['channel', 'status']);
        });

        Schema::create('notification_template_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedSmallInteger('version_number');
            $table->text('body');
            $table->string('subject')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('notification_templates')->onDelete('cascade');
            $table->unique(['template_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_template_versions');
        Schema::dropIfExists('notification_templates');
    }
};