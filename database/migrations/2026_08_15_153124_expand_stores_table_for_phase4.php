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
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'store_code')) {
                $table->string('store_code')->nullable()->after('id');
            }
            if (!Schema::hasColumn('stores', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (!Schema::hasColumn('stores', 'region')) {
                $table->string('region')->nullable()->after('city');
            }
            if (!Schema::hasColumn('stores', 'pin_code')) {
                $table->string('pin_code')->nullable()->after('region');
            }
            if (!Schema::hasColumn('stores', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable()->after('merchant_id');
                $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('stores', 'geo_coordinates')) {
                $table->json('geo_coordinates')->nullable()->after('declared_longitude');
            }
            if (!Schema::hasColumn('stores', 'working_hours')) {
                $table->json('working_hours')->nullable()->after('geo_coordinates');
            }
            if (!Schema::hasColumn('stores', 'deactivation_reason')) {
                $table->text('deactivation_reason')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'store_code',
                'city',
                'region',
                'pin_code',
                'manager_id',
                'geo_coordinates',
                'working_hours',
                'deactivation_reason'
            ]);
        });
    }
};
