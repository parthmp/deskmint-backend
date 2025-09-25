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
        Schema::create('settings_invoice', function (Blueprint $table) {
            $table->id();

			/* general settings */
			$table->string('template_name', 100)->default('');
			$table->integer('font_size')->default(14);
			$table->integer('logo_size')->default(100);
			$table->string('primary_color', 10)->default('#11898b');
			$table->string('secondary_color', 10)->default('#118b65');
			$table->boolean('e_invoice_on');

			/* client details */
			$table->text('client_details_json')->nullable();
			$table->text('company_details_json')->nullable();
			$table->text('company_address_details_json')->nullable();
			$table->text('invoice_details_json')->nullable();
			$table->text('product_columns_json')->nullable();
			$table->text('total_fields_json')->nullable();

			/* generated numbers */
			$table->string('invoice_number_prefix', 255)->default('');
			$table->string('reset_number_prefix', 255)->default('');
			

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings_invoice');
    }
};
