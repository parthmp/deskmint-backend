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
        Schema::create('additional_product_columns_field_values_ri', function (Blueprint $table) {

            $table->id();
			$table->uuid('row_uuid')->default('');
			
			$table->unsignedBigInteger('recurring_invoice_id');
			$table->foreign('recurring_invoice_id', 'riid_ri_fk')->references('id')->on('recurring_invoices')->onDelete('cascade');

			$table->unsignedBigInteger('apc_field_id');
			$table->foreign('apc_field_id', 'apcfid_apcf_fk')->references('id')->on('additional_product_columns_fields')->onDelete('cascade');

			$table->string('value', 255)->default('');

			$table->unique(['row_uuid', 'recurring_invoice_id', 'apc_field_id'], 'unique_apcfv');

			$table->softDeletes();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_product_columns_field_values_ri');
    }
};
