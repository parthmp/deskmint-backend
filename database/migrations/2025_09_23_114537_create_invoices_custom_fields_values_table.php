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
        Schema::create('invoices_custom_fields_values', function (Blueprint $table) {
          	
			$table->id();

			$table->unsignedBigInteger('invoice_id');
			$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

			$table->unsignedBigInteger('invoices_custom_field_id');
			$table->foreign('invoices_custom_field_id')->references('id')->on('invoices_custom_fields')->onDelete('cascade');

			$table->text('field_value');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices_custom_fields_values');
    }
};
