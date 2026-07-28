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
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
           	$table->dropColumn('invoice_id');
			$table->renameColumn('payment_method', 'payment_gateway');
			$table->dropForeign(['voided_by']);
           	$table->dropColumn('voided_by');
           	$table->dropColumn('voided_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {

			$table->unsignedBigInteger('invoice_id')->nullable()->after('company_id');
			$table->foreign('invoice_id')->references('id')->on('invoices');

			$table->renameColumn('payment_gateway', 'payment_method');

            $table->unsignedBigInteger('voided_by')->nullable()->after('is_echeck');
			$table->foreign('voided_by')->references('id')->on('users');

			$table->datetime('voided_at')->nullable()->after('paid_at');

        });
    }
};
