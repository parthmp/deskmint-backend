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
            $table->decimal('gateway_fees_amount', 10, 2)->default(0)->after('amount');
            $table->decimal('received_amount', 10, 2)->default(0)->after('gateway_fees_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('gateway_fees_amount');
            $table->dropColumn('received_amount');
        });
    }
};
