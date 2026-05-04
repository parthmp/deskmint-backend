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
            $table->renameColumn('is_success', 'is_payment_captured');
            $table->tinyInteger('is_approved')->default(0)->after('additional_details');

			$table->renameColumn('additional_details', 'payment_approved_details');
            $table->text('payment_captured_details')->nullable()->after('payment_approved_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function(Blueprint $table){
			$table->renameColumn('is_payment_captured', 'is_success');
			$table->dropColumn('is_approved');

			$table->renameColumn('payment_approved_details', 'additional_details');
			$table->dropColumn('payment_captured_details');
		});
    }
};
