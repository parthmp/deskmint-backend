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
        Schema::create('transaction_gateway_details', function (Blueprint $table) {
            $table->id();
			$table->unsignedBigInteger('transaction_id');
			$table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
			$table->text('payment_approved_details')->nullable();
			$table->text('payment_captured_details')->nullable();
			$table->text('echeck_pending_details')->nullable();
			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_gateway_details');
    }
};
