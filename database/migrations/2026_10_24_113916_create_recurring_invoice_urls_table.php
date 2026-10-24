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
        Schema::create('recurring_invoice_urls', function (Blueprint $table) {
            
			$table->id();

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->unsignedBigInteger('recurring_invoice_id');
			$table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices')->onDelete('cascade');
			$table->smallInteger('payment_gateway');
			$table->decimal('total', 10, 2);
			$table->string('url', 1000);
			$table->softDeletes();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoice_urls');
    }
};
