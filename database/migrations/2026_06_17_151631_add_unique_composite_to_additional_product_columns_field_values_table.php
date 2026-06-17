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
            $table->unique(['row_uuid', 'invoice_id', 'apc_field_id'], 'apcfv_row_uuid_invoice_apc_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additional_product_columns_field_values', function (Blueprint $table) {
            $table->dropUnique('apcfv_row_uuid_invoice_apc_unique');
        });
    }
};
