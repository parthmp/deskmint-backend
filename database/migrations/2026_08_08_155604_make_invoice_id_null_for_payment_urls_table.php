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
		Schema::table('payment_urls', function (Blueprint $table) {

        	$table->dropForeign(['invoice_id']);
			$table->dropColumn('invoice_id');

			$table->unsignedBigInteger('invoice_id')->after('id')->nullable(true);
			$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_urls', function (Blueprint $table) {
			
           	$table->dropForeign(['invoice_id']);
			$table->dropColumn('invoice_id');

			$table->unsignedBigInteger('invoice_id')->after('id')->nullable(false);
			$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

        });
    }
};
