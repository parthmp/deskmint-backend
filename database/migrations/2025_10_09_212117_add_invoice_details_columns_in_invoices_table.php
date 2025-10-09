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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number', 255)->after('id');
            $table->datetime('invoice_date')->after('invoice_number');
            $table->datetime('due_date')->after('invoice_date');
            $table->double('po_number')->after('due_date');
            $table->double('amount')->after('po_number');
            $table->double('balance_due')->after('amount');
            $table->double('total')->after('balance_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
            $table->dropColumn('invoice_date');
            $table->dropColumn('due_date');
            $table->dropColumn('po_number');
            $table->dropColumn('amount');
            $table->dropColumn('balance_due');
            $table->dropColumn('total');
        });
    }
};
