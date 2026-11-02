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
            $table->smallInteger('frequency')->after('recurring_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoice_urls', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
    }
};
