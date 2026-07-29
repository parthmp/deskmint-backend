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
        Schema::create('clients_custom_fields_values', function (Blueprint $table) {
            
			$table->id();

			$table->unsignedBigInteger('clients_custom_field_id');
			$table->foreign('clients_custom_field_id')->references('id')->on('clients_custom_fields')->onDelete('cascade');

			$table->text('field_value');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients_custom_fields_values');
    }
};
