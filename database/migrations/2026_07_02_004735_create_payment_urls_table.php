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
        Schema::create('payment_urls', function (Blueprint $table) {
            $table->id();
			$table->unsignedBigInteger('transaction_id');
			$table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
			$table->string('gateway_url_identifier', 255);
			$table->string('url', 600);
			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_urls');
    }
};
