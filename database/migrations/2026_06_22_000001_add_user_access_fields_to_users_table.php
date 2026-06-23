<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('merchant_scope')->nullable()->after('merchant_id');
            $table->string('password_expiry_policy')->default('default')->after('password_changed_at');
            $table->date('activation_date')->nullable()->after('password_expiry_policy');
            $table->date('deactivation_date')->nullable()->after('activation_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'merchant_scope',
                'password_expiry_policy',
                'activation_date',
                'deactivation_date',
            ]);
        });
    }
};
