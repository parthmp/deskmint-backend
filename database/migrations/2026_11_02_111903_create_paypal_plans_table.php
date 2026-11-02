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
        Schema::create('paypal_plans', function (Blueprint $table) {
            
			$table->id();
			$table->uuid('uuid')->unique();
			$table->string('paypal_product_id');
			$table->string('paypal_plan_id', 255)->unique();

			$table->string('interval_unit', 100);
			$table->integer('interval_count');

			$table->unsignedBigInteger('currency_id');
			$table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
			
            $table->timestamps();

			$table->unique(
				['paypal_plan_id', 'interval_unit', 'interval_count', 'currency_id'],
				'paypal_plans_lookup_unique'
			);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paypal_plans');
    }
};
