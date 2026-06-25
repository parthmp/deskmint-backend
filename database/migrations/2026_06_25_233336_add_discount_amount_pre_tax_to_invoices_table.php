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
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('discount_amount', 'discount_amount_post_tax');
            $table->double('discount_amount_pre_tax')->default(0)->after('discount_amount_post_tax');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->renameColumn('discount_amount_post_tax', 'discount_amount');
            $table->dropColumn('discount_amount_pre_tax');
        });
    }
};
