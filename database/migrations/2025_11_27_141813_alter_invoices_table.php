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

        Schema::table('invoices', function (Blueprint $table) {
			
			$table->unsignedBigInteger('client_id')->after('id');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

			$table->decimal('discount', 10, 2)->default(0)->after('po_number');
            $table->tinyInteger('discount_type')->after('discount'); // percentage or amount
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_type');

			$table->dropColumn('amount');

			// subtotal should come after discount_amount for logical ordering
            $table->decimal('subtotal', 10, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('subtotal');

			// invoice text and flags
            $table->text('invoice_terms')->nullable()->after('total');
            $table->boolean('send_email')->default(false)->after('invoice_terms');
            $table->string('payment_method', 100)->default('')->after('send_email');

			$table->json('settings_snapshot')->after('scan_chars');

			$table->decimal('total', 10, 2)->change();
			
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
          	$table->dropForeign(['client_id']);
            $table->dropColumn('client_id');

            $table->dropColumn('discount');
            $table->dropColumn('discount_type');
            $table->dropColumn('discount_amount');

            $table->dropColumn('subtotal');
            $table->dropColumn('tax_amount');

            $table->dropColumn('invoice_terms');
            $table->dropColumn('send_email');
            $table->dropColumn('payment_method');
            $table->dropColumn('settings_snapshot');
		  	$table->double('amount')->after('po_number');
        });
    }
};
