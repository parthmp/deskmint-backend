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
        Schema::table('clients_custom_fields_values', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->after('id');
			$table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients_custom_fields_values', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropcolumn('client_id');
        });
    }
};
