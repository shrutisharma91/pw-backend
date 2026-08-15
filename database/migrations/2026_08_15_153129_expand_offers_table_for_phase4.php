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
        Schema::table('offers', function (Blueprint $table) {
            if (!Schema::hasColumn('offers', 'target_categories')) {
                $table->json('target_categories')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('offers', 'target_stores')) {
                $table->json('target_stores')->nullable()->after('target_categories');
            }
            if (!Schema::hasColumn('offers', 'min_cart_value')) {
                $table->decimal('min_cart_value', 12, 2)->nullable()->after('target_stores');
            }
            if (!Schema::hasColumn('offers', 'budget_cap')) {
                $table->decimal('budget_cap', 12, 2)->nullable()->after('min_cart_value');
            }
            if (!Schema::hasColumn('offers', 'approval_status')) {
                $table->string('approval_status')->default('Pending')->after('budget_cap');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn([
                'target_categories',
                'target_stores',
                'min_cart_value',
                'budget_cap',
                'approval_status'
            ]);
        });
    }
};
