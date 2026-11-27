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
        Schema::table('refund_logs', function (Blueprint $table) {
            $table->dropColumn('status');
			$table->string('ARN', 100)->default('')->after('currency_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refund_logs', function (Blueprint $table) {
            $table->smallInteger('status')->after('currency_id');
			$table->dropColumn('ARN');
        });
    }
};
