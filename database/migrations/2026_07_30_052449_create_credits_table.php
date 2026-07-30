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
        Schema::create('credits', function (Blueprint $table) {

            $table->id();

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->unsignedBigInteger('client_id');
			$table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
			
			$table->unsignedBigInteger('currency_id');
			$table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');

			$table->tinyInteger('status');
			$table->decimal('amount', 10, 2)->default(0);
			$table->decimal('amount_left_to_be_applied', 10, 2)->default(0);

			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
