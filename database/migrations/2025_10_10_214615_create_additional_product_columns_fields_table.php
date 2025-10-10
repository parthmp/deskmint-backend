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
        Schema::create('additional_product_columns_fields', function (Blueprint $table) {

            $table->id();

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->string('label', 255)->default('');
			$table->string('type', 255)->default('');
			$table->double('tax_rate')->default(0);

			$table->softDeletes();

            $table->timestamps();
			
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_product_columns_fields');
    }
};
