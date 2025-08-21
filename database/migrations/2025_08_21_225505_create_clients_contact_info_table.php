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
        Schema::create('clients_contact_info', function (Blueprint $table) {
            $table->id();

			$table->unsignedBigInteger('client_id');
			$table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

			$table->string('first_name', 100);
			$table->string('last_name', 100);
			$table->string('email', 100);
			$table->string('phone', 50);

			$table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients_contact_info');
    }
};
