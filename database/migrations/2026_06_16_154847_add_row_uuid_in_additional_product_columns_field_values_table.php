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
        Schema::table('additional_product_columns_field_values', function (Blueprint $table) {
            $table->string('row_uuid', 100)->default('')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additional_product_columns_field_values', function (Blueprint $table) {
            $table->dropColumn('row_uuid');
        });
    }
};
