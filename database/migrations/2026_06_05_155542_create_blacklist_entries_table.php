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
        Schema::create('blacklist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // PAN, mobile, device, Aadhaar, bank_account
            $table->string('value');
            $table->text('reason');
            $table->string('source')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('severity')->default('High');
            $table->string('status')->default('Active'); // Active, Removed, Whitelisted
            $table->foreignId('override_approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklist_entries');
    }
};
