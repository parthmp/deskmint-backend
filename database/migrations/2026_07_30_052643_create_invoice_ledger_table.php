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
        Schema::create('invoice_ledger', function (Blueprint $table) {

            $table->id();

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->unsignedBigInteger('invoice_id');
			$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
			
			$table->unsignedBigInteger('payment_id')->nullable();
			$table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');

			$table->unsignedBigInteger('credit_id')->nullable();
			$table->foreign('credit_id')->references('id')->on('credits')->onDelete('cascade');

			$table->decimal('applied_amount_from_payments', 10, 2)->default(0);
			$table->decimal('applied_amount_from_credits', 10, 2)->default(0);;
			$table->decimal('total_applied', 10, 2)->default(0);;

			$table->softDeletes();
            $table->timestamps();
			
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_ledger');
    }
};
