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
        Schema::table('transaction_references', function (Blueprint $table) {

            $table->unsignedBigInteger('invoice_id')->nullable()->change();

			$table->unsignedBigInteger('payment_request_id')->after('invoice_id')->nullable();
			$table->foreign('payment_request_id')->references('id')->on('payment_requests')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_references', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
			$table->dropForeign(['payment_request_id']);
			$table->dropColumn('payment_request_id');
        });
    }
};
