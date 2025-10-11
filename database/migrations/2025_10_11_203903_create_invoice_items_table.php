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
        Schema::create('invoice_items', function (Blueprint $table){

            $table->id();

			$table->unsignedBigInteger('product_id');
			$table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

			$table->string('description', 500)->default('');
			$table->double('unit_price')->default(0);
			$table->integer('quantity')->default(0);
			$table->double('discount')->default(0);
			$table->double('tax')->default(0);
			$table->double('line_total')->default(0);
			$table->double('gross_line_total')->default(0);
			$table->double('total')->default(0);

            $table->timestamps();
			
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
