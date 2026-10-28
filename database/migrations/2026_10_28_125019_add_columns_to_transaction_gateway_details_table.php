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
        Schema::table('transaction_gateway_details', function (Blueprint $table) {
            $table->text('subscription_sale_deails')->after('payment_captured_details')->nullable();
            $table->text('subscription_activated_deails')->after('subscription_sale_deails')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_gateway_details', function (Blueprint $table) {
            $table->dropColumn('subscription_sale_deails');
            $table->dropColumn('subscription_activated_deails');
        });
    }
};
