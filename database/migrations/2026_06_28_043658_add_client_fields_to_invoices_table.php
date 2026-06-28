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
        Schema::table('invoices', function (Blueprint $table) {

            $table->string('first_name', 255)->after('client_id');
            $table->string('last_name', 255)->after('first_name');
            $table->string('full_name', 255)->after('last_name');
            $table->string('client_company', 255)->after('full_name');

			$table->unsignedBigInteger('currency_id')->after('client_company');
			$table->foreign('currency_id')->references('id')->on('currencies')->onDelete('restrict');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            $table->dropColumn('full_name');
            $table->dropColumn('client_company');
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
