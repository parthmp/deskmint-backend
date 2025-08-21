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
        Schema::create('clients', function (Blueprint $table){
			
            $table->id();
			$table->string('first_name', 100);
			$table->string('last_name', 100);
			$table->string('tax_number', 100);
			$table->string('website', 100);
			$table->string('email', 100);
			$table->string('phone', 50);
			
			$table->string('billing_street', 150);
			$table->string('billing_apt', 150);
			$table->string('billing_city', 50);
			$table->string('billing_state', 50);
			$table->string('billing_postal_code', 50);
			$table->unsignedBigInteger('billing_country_id');
			$table->foreign('billing_country_id')->references('id')->on('countries')->onDelete('cascade');

			$table->string('shipping_street', 150);
			$table->string('shipping_apt', 150);
			$table->string('shipping_city', 50);
			$table->string('shipping_state', 50);
			$table->string('shipping_postal_code', 50);
			$table->unsignedBigInteger('shipping_country_id');
			$table->foreign('shipping_country_id')->references('id')->on('countries')->onDelete('cascade');

			$table->unsignedBigInteger('currency_id');
			$table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');

			$table->string('payment_terms', 50);
			$table->integer('quote_valid_days');
			$table->tinyInteger('send_reminders');
			$table->string('size', 50);
			
			$table->unsignedBigInteger('industry_id');
			$table->foreign('industry_id')->references('id')->on('industries')->onDelete('cascade');

			$table->softDeletes();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
