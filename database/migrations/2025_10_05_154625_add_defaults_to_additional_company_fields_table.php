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
        Schema::table('additional_company_fields', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->string('label', 255)->default('')->change();
            $table->string('value', 255)->default('')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additional_company_fields', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
			$table->string('label', 255)->default(null)->change();
			$table->string('value', 255)->default(null)->change();
        });
    }
};
