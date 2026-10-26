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
        Schema::create('subscription_plans', function (Blueprint $table) {

			$table->id();
			$table->uuid('uuid')->unique();

			$table->unsignedBigInteger('recurring_invoice_id')->nullable();
			$table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices')->onDelete('cascade');

			$table->smallInteger('payment_gateway');
			
			$table->string('gateway_subscription_id', 255)->nullable()->index();

			$table->string('plan_id', 255)->default('')->index();

			$table->string('payer_id', 255)->default('');
			$table->string('customer_id', 255)->default('');

			$table->softDeletes();
			$table->timestamps();

		});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
