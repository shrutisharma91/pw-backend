<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->enum('document_type', ['kyc', 'agreement', 'invoice', 'statement', 'enach', 'esign', 'other']);
            $table->string('entity_type', 50);
            $table->unsignedBigInteger('entity_id');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('mime_type', 100);
            $table->enum('status', ['pending_ocr', 'ocr_done', 'virus_clean', 'quarantined', 'archived'])->default('pending_ocr');
            $table->enum('ocr_status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->longText('ocr_text')->nullable();
            $table->enum('virus_scan_status', ['pending', 'clean', 'infected'])->default('pending');
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['document_type', 'status']);
            $table->index('deleted_at');
            if (config('database.default') !== 'sqlite') {
                $table->fullText('ocr_text');
            }
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedSmallInteger('version');
            $table->string('storage_path');
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });

        Schema::create('document_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('shared_by');
            $table->string('purpose');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
    }
};