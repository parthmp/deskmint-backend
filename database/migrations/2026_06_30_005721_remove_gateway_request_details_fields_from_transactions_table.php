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
            $table->dropColumn('payment_approved_details');
            $table->dropColumn('payment_captured_details');
            $table->dropColumn('echeck_pending_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->text('payment_approved_details')->after('token_id_identifier');
            $table->text('payment_captured_details')->after('payment_approved_details');
            $table->text('echeck_pending_details')->after('payment_captured_details');
        });
    }
};
