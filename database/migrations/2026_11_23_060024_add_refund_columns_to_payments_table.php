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
            $table->decimal('applied_amount_refund', 10, 2)->default(0)->after('amount_left_to_be_applied');
            $table->decimal('unapplied_amount_refund', 10, 2)->default(0)->after('applied_amount_refund');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('applied_amount_refund');
            $table->dropColumn('unapplied_amount_refund');
        });
    }
};
