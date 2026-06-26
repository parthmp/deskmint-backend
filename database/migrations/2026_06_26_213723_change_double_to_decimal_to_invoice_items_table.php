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
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->default(0)->change();
			$table->decimal('discount', 10, 4)->default(0)->change();
			$table->decimal('discount_amount', 10, 2)->default(0)->change();
			$table->decimal('tax', 10, 4)->default(0)->change();
			$table->decimal('line_total', 10, 2)->default(0)->change();
			
			$table->decimal('tax_amount', 10, 2)->default('0.00')->change();
			$table->decimal('line_subtotal', 10, 2)->default('0.00')->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->double('unit_price')->default(0)->change();
			$table->double('discount')->default(0)->change();
			$table->double('discount_amount')->default(0)->change();
			$table->double('tax')->default(0)->change();
			$table->double('line_total')->default(0)->change();
			
			$table->decimal('tax_amount', 8, 2)->default('0.00')->change();
			$table->decimal('line_subtotal', 8, 2)->default('0.00')->change();
        });
    }
};
