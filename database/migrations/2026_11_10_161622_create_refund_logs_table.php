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
        Schema::create('refund_logs', function (Blueprint $table) {

            $table->id();

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->unsignedBigInteger('invoice_id')->nullable();
			$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

			$table->unsignedBigInteger('payment_id')->nullable();
			$table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');

			$table->unsignedBigInteger('credit_id')->nullable();
			$table->foreign('credit_id')->references('id')->on('credits')->onDelete('cascade');

			$table->decimal('amount', 10, 2);

			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_logs');
    }
};
