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
        Schema::create('recurring_invoice_items', function (Blueprint $table) {
            $table->id();
			$table->uuid('row_uuid')->default('');

			$table->unsignedBigInteger('recurring_invoice_id');
			$table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices')->onDelete('cascade');

			$table->unsignedBigInteger('product_id');
			$table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

			$table->string('description', 500)->default('');
			$table->decimal('unit_price', 10, 2)->default(0);
			$table->integer('quantity')->default(0);
			$table->decimal('discount', 10, 4)->default(0);
			$table->decimal('discount_amount', 10, 2)->default(0);
			$table->decimal('tax', 10, 4)->default(0);
			$table->decimal('tax_amount', 10, 2)->default(0);
			$table->decimal('line_total', 10, 2)->default(0);
			$table->decimal('line_subtotal', 10, 2)->default(0);

			$table->unique(['row_uuid', 'recurring_invoice_id']);

			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_items');
    }
};
