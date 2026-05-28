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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('gst_cert_url')->nullable();
            $table->string('pan_url')->nullable();
            $table->string('aadhaar_url')->nullable();
            $table->string('cheque_url')->nullable();
            $table->string('shop_license_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['gst_cert_url', 'pan_url', 'aadhaar_url', 'cheque_url', 'shop_license_url']);
        });
    }
};
