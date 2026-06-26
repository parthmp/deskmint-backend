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
            $table->decimal('discount_amount_pre_tax', 10, 2)->default(0)->change();
			$table->decimal('balance_due', 10, 2)->default(0)->change();
			
			$table->decimal('discount', 10, 4)->default('0.0000')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->double('discount_amount_pre_tax')->default(0)->change();
			$table->double('balance_due')->default(0)->change();
			
			$table->decimal('discount', 10, 2)->default('0.00')->change();
        });
    }
};
