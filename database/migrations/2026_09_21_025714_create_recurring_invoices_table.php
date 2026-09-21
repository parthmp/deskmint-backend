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
        Schema::create('recurring_invoices', function (Blueprint $table) {
            
			$table->id();
			$table->uuid('uuid');

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
			
			$table->unsignedBigInteger('client_id');
			$table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

			$table->unsignedBigInteger('currency_id');
			$table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');

			$table->string('po_number', 255)->default('');

			$table->decimal('discount')->default(0);
			$table->tinyInteger('discount_type');

			$table->decimal('discount_amount_post_tax')->default(0);
			$table->decimal('discount_amount_pre_tax')->default(0);
			$table->decimal('subtotal')->default(0);
			$table->decimal('tax_amount')->default(0);
			
			$table->decimal('total')->default(0);
			$table->tinyInteger('status')->default(1);
			$table->text('invoice_terms');
			$table->smallInteger('payment_gateway');
			$table->string('timezone', 255);
			$table->dateTime('next_invoice_on')->nullable();
			$table->dateTime('sent_at')->nullable();
			$table->dateTime('hidden_sent_at')->nullable();

			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
