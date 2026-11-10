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
        Schema::table('invoice_ledger', function (Blueprint $table) {
            $table->decimal('refund_amount_from_payments', 10, 2)->default(0)->after('total_applied');
            $table->decimal('refund_amount_from_credits', 10, 2)->default(0)->after('refund_amount_from_payments');
            $table->decimal('total_applied_refund', 10, 2)->default(0)->after('refund_amount_from_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_ledger', function (Blueprint $table) {
            $table->dropColumn('refund_amount_from_payments');
            $table->dropColumn('refund_amount_from_credits');
            $table->dropColumn('total_applied_refund');
        });
    }
};
