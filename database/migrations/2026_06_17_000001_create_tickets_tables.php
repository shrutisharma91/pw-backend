<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 30)->unique();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('source_role', ['merchant', 'customer', 'store', 'lender_ops', 'internal'])->default('merchant');
            $table->enum('category', ['dispute', 'complaint', 'technical', 'billing', 'kyc', 'loan', 'settlement', 'agreement', 'other'])->default('other');
            $table->enum('priority', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'waiting', 'resolved', 'closed', 'escalated'])->default('open');
            $table->enum('sla_state', ['ok', 'at_risk', 'breached'])->default('ok');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('reporter_name');
            $table->string('reporter_email')->nullable();
            $table->string('reporter_phone', 20)->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('resolution_category', 50)->nullable();
            $table->text('resolution_note')->nullable();
            $table->unsignedTinyInteger('csat_score')->nullable();
            $table->text('csat_comment')->nullable();
            $table->timestamp('csat_requested_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->unsignedBigInteger('escalated_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['source_role', 'category']);
            $table->index(['sla_state', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->enum('visibility', ['public', 'internal'])->default('public');
            $table->enum('author_type', ['admin', 'merchant', 'customer', 'system'])->default('admin');
            $table->unsignedBigInteger('author_id')->nullable();
            $table->string('author_name');
            $table->text('body');
            $table->boolean('is_redacted')->default(false);
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('ticket_messages')->onDelete('set null');
        });

        Schema::create('ticket_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('cascade');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_links');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
