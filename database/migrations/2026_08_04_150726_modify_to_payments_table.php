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
        Schema::table('payments', function (Blueprint $table) {
			
            $table->unsignedBigInteger('transaction_id')->nullable()->change();

			$table->unsignedBigInteger('payment_type_id')->after('transaction_id');
			$table->foreign('payment_type_id')->references('id')->on('payment_types')->onDelete('cascade');
			
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable(false)->change();
			$table->dropForeign(['payment_types']);
			$table->dropColumn('payment_types');
        });
    }
};
