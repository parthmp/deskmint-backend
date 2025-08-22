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
        Schema::table('clients', function (Blueprint $table){

            $table->string('tax_number', 100)->default('')->change();
            $table->string('website', 100)->default('')->change();
            $table->string('phone', 50)->default('')->change();
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void{

        Schema::table('clients', function (Blueprint $table){
            $table->string('tax_number', 100)->change();
            $table->string('website', 100)->change();
            $table->string('phone', 50)->change();
        });
		
    }
};