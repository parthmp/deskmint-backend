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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->smallInteger('frequency')->after('recurring_invoice_id');
			$table->integer('custom_frequency_days')->default(0)->after('frequency');
			$table->decimal('total', 10, 2)->after('custom_frequency_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('frequency');
			$table->dropColumn('custom_frequency_days');
			$table->dropColumn('total');
        });
    }
};
