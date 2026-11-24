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
            $table->uuid('uuid')->after('id');
            $table->string('gateway_refund_id')->nullable()->after('uuid');
            $table->smallInteger('status')->after('currency_id');
			$table->unsignedBigInteger('transaction_id')->after('status')->nullable();
			$table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refund_logs', function (Blueprint $table) {
            $table->dropColumn('uuid');
            $table->dropColumn('gateway_refund_id');
            $table->dropColumn('status');
			$table->dropForeign(['transaction_id']);
			$table->dropColumn('transaction_id');
        });
    }
};
