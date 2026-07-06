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

            $table->dropColumn('is_paid');
			$table->tinyInteger('status')->default(1)->after('total');
			$table->string('pdf_file', 255)->default('')->after('status');
			$table->string('xml_file', 255)->default('')->after('pdf_file');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->tinyInteger('is_paid')->default(0)->after('total');
			$table->dropColumn('status');
			$table->dropColumn('pdf_file');
			$table->dropColumn('xml_file');
        });
    }
};
