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
        Schema::create('additional_product_columns_field_values', function (Blueprint $table) {

            $table->id();

			$table->unsignedBigInteger('apc_field_id');
			$table->foreign('apc_field_id')->references('id')->on('additional_product_columns_fields')->onDelete('cascade');

			$table->string('value', 255)->default('');

			$table->softDeletes();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_product_columns_field_values');
    }
};
