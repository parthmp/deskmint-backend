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
        Schema::create('transaction_references', function (Blueprint $table) {
            
			$table->id();

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->unsignedBigInteger('client_id');
			$table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

			$table->unsignedBigInteger('transaction_id')->nullable();
			$table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');

			$table->unsignedBigInteger('invoice_id');
			$table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_references');
    }
};
