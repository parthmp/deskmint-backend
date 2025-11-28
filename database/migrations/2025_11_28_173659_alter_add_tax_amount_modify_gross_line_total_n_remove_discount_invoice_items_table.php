<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
			
			$table->dropColumn('discount');

			$table->decimal('tax_amount')->default(0)->after('tax');
			$table->decimal('gross_line_total')->default(0)->change();
			$table->renameColumn('gross_line_total', 'line_subtotal');

			$table->softDeletes();

			
		});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table) {
			$table->double('discount')->default(0)->after('quantity');
			$table->dropColumn('tax_amount');
			$table->renameColumn('line_subtotal', 'gross_line_total');
			$table->double('gross_line_total')->default(0)->change();
		});
    }
};
