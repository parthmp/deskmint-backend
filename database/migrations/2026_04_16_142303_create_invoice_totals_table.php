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
        Schema::create('invoice_totals', function (Blueprint $table) {
            $table->id();
			$table->unsignedBigInteger('invoice_id');
			$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
			$table->double('subtotal');
			$table->double('discount_amount');
			$table->double('tax_amount');
			$table->double('paid_to_date');
			$table->double('balance_due');
			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_totals');
    }
};
