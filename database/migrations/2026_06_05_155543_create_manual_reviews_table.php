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
        Schema::create('manual_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications');
            $table->decimal('risk_score', 8, 2);
            $table->string('status')->default('Pending'); // Pending, Approved, Rejected, Escalated
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('sla_deadline')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_reviews');
    }
};
