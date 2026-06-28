<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('reassigned_at')->nullable()->after('escalated_to');
            $table->unsignedBigInteger('reassigned_by')->nullable()->after('reassigned_at');

            $table->index(['reassigned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['reassigned_at']);
            $table->dropColumn(['reassigned_at', 'reassigned_by']);
        });
    }
};
