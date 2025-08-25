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
            $table->dropColumn('order_column_on_index_page');
            $table->dropColumn('show_on_index_page');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients_custom_fields', function (Blueprint $table) {
            $table->smallInteger('order_column_on_index_page')->default(0);
			$table->tinyInteger('show_on_index_page')->default(1);
        });
    }
};
