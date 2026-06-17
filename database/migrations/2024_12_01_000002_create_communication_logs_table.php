<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['sms', 'email', 'whatsapp', 'push']);
            $table->string('recipient');
            $table->string('template_key', 100)->nullable();
            $table->enum('provider', ['msg91', 'ses', 'meta_wa', 'firebase', 'kaleyra', 'sendgrid', 'interakt']);
            $table->string('provider_message_id')->nullable();
            $table->enum('status', ['sent', 'delivered', 'read', 'clicked', 'failed', 'bounced'])->default('sent');
            $table->string('failure_reason')->nullable();
            $table->decimal('cost', 10, 4)->default(0);
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status', 'sent_at']);
            $table->index(['template_key', 'sent_at']);
            $table->index(['merchant_id', 'sent_at']);
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_logs');
    }
};