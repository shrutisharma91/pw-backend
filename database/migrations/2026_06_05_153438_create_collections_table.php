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
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications');
            $table->string('dpd_bucket'); // 0-30, 31-60, 61-90, 90+
            $table->decimal('overdue_amount', 10, 2);
            $table->foreignId('agent_id')->nullable()->constrained('users'); // Assigned collection agent
            $table->string('status')->default('Pending');
            $table->string('npa_status')->nullable(); // Foreclosure, Settled, NOC Generated, Written-off
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
