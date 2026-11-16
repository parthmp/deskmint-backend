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
        Schema::create('recurring_invoices_flat', function (Blueprint $table) {
            $table->id();
			$table->unsignedBigInteger('recurring_invoice_id');
			$table->foreign('recurring_invoice_id')->references('id')->on('recurring_invoices')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices_flat');
    }
};
