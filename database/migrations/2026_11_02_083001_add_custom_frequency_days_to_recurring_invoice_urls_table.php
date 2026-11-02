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
        Schema::table('recurring_invoice_urls', function (Blueprint $table) {
            $table->integer('custom_frequency_days')->default(0)->after('frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoice_urls', function (Blueprint $table) {
            $table->dropColumn('custom_frequency_days');
        });
    }
};
