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
        Schema::create('recurring_invoices_custom_fields_values', function (Blueprint $table) {

           	$table->id();

			$table->unsignedBigInteger('recurring_invoice_id');
			$table->foreign('recurring_invoice_id', 'ricfv_rec_inv_fk')->references('id')->on('recurring_invoices')->onDelete('cascade');

			$table->unsignedBigInteger('recurring_invoices_custom_field_id');
			$table->foreign('recurring_invoices_custom_field_id', 'ricfv_cust_field_fk')->references('id')->on('recurring_invoices_custom_fields')->onDelete('cascade');

			$table->text('field_value');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices_custom_fields_values');
    }
};
