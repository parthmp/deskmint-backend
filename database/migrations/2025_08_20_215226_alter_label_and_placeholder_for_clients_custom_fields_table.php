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
        Schema::table('clients_custom_fields', function (Blueprint $table) {
			$table->string('label', 255)->change();
			$table->string('placeholder', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients_custom_fields', function (Blueprint $table) {
			$table->string('label', 20)->change();
			$table->string('placeholder', 20)->change();
        });
    }
};
