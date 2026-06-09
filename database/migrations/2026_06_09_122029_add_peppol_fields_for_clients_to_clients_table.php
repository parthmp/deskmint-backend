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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_company_name', 255)->default('')->after('company_id');
            $table->string('peppol_identifier', 255)->default('')->after('shipping_country_id');
            $table->string('peppol_scheme', 10)->default('')->after('peppol_identifier');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('client_company_name');
            $table->dropColumn('peppol_identifier');
            $table->dropColumn('peppol_scheme');
        });
    }
};
