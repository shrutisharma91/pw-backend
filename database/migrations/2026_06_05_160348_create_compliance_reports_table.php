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
        Schema::create('compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type'); // RBI monthly, DPDP access
            $table->string('status')->default('Pending');
            $table->foreignId('generated_by')->nullable()->constrained('users');
            $table->string('file_url')->nullable();
            $table->json('parameters')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_reports');
    }
};
