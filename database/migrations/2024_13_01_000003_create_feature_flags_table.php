<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key', 100)->unique();
            $table->text('description')->nullable();
            $table->enum('type', ['boolean', 'percentage', 'cohort'])->default('boolean');
            $table->text('default_value');
            $table->enum('rollout_status', ['on', 'off', 'partial'])->default('off');
            $table->unsignedTinyInteger('rollout_percent')->default(0);
            $table->json('cohort_rules')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('killed_at')->nullable();
            $table->unsignedBigInteger('killed_by')->nullable();
            $table->text('kill_reason')->nullable();
            $table->timestamps();

            $table->index(['rollout_status', 'key']);
        });

        Schema::create('ab_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('flag_id');
            $table->string('name');
            $table->text('variant_a_value');
            $table->text('variant_b_value');
            $table->unsignedTinyInteger('traffic_split');
            $table->string('metric', 100);
            $table->enum('status', ['scheduled', 'active', 'ended', 'cancelled'])->default('scheduled');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('flag_id')->references('id')->on('feature_flags')->onDelete('cascade');
            $table->index(['flag_id', 'status']);
        });

        Schema::create('ab_test_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_id');
            $table->string('variant', 1);
            $table->unsignedBigInteger('entity_id');
            $table->boolean('converted')->default(false);
            $table->timestamp('event_at')->useCurrent();

            $table->foreign('test_id')->references('id')->on('ab_tests')->onDelete('cascade');
            $table->index(['test_id', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_test_events');
        Schema::dropIfExists('ab_tests');
        Schema::dropIfExists('feature_flags');
    }
};