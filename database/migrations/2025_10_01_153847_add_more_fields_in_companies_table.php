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
        Schema::table('companies', function (Blueprint $table) {
            
			$table->string('id_number', 255)->default('')->after('company_name');
            $table->string('gst_vat_number', 255)->default('')->after('id_number');
            $table->string('classification', 255)->default('')->after('gst_vat_number');
            $table->string('website', 255)->default('')->after('classification');
            $table->string('email', 255)->default('')->after('website');
            $table->string('phone', 255)->default('')->after('email');
            
			$table->string('address_street', 255)->default('')->after('phone');
			$table->string('apt', 255)->default('')->after('address_street');
			$table->string('city', 255)->default('')->after('apt');
			$table->string('state', 255)->default('')->after('city');
			$table->string('postal_code', 255)->default('')->after('state');

			$table->unsignedBigInteger('country_id')->nullable()->after('postal_code');
			$table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');

			$table->string('logo', 255)->default('')->after('country_id');

			$table->text('invoice_terms')->nullable()->after('logo');
			$table->text('invoice_footer')->nullable()->after('invoice_terms');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {

            $table->dropColumn('id_number');
            $table->dropColumn('gst_vat_number');
            $table->dropColumn('classification');
            $table->dropColumn('website');
            $table->dropColumn('email');
            $table->dropColumn('phone');

            $table->dropColumn('address_street');
            $table->dropColumn('apt');
            $table->dropColumn('city');
            $table->dropColumn('state');
            $table->dropColumn('postal_code');

            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');

            $table->dropColumn('logo');

            $table->dropColumn('invoice_terms');
            $table->dropColumn('invoice_footer');

        });
    }
};
