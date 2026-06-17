<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('definition');
            $table->string('chart_type', 30)->default('table');
            $table->boolean('is_shared')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['created_by', 'is_shared']);
        });

        Schema::create('custom_report_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedSmallInteger('version_number');
            $table->json('definition');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('custom_reports')->onDelete('cascade');
        });

        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id')->unique();
            $table->string('frequency', 20);
            $table->json('recipients');
            $table->string('format', 10);
            $table->time('send_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('report_id')->references('id')->on('custom_reports')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('custom_report_versions');
        Schema::dropIfExists('custom_reports');
    }
};