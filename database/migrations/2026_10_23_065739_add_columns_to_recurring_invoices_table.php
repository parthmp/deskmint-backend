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
            $table->boolean('send_invoice_email')->after('timezone')->default(true);
            $table->dateTime('last_invoice_created_at')->after('send_invoice_email')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropColumn('send_invoice_email');
            $table->dropColumn('last_invoice_created_at');
        });
    }
};
