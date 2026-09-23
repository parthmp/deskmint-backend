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
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->tinyInteger('frequency')->default(1)->after('payment_gateway');
            $table->integer('custom_frequency_days')->default(0)->after('frequency');
            $table->boolean('mark_paid_automatically')->after('custom_frequency_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropColumn('frequency');
            $table->dropColumn('custom_frequency_days');
            $table->dropColumn('mark_paid_automatically');
        });
    }
};
