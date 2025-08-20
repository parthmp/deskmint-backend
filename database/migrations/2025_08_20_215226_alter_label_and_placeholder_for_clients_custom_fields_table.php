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
			$table->text('label', 255)->change();
			$table->text('placeholder', 255)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients_custom_fields', function (Blueprint $table) {
			$table->text('label', 20)->change();
			$table->text('placeholder', 20)->change();
        });
    }
};
