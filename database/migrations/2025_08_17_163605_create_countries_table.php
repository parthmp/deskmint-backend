<?php

use Database\Seeders\CountriesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
			$table->text('country_name');
			$table->string('country_code', 20);
			$table->string('phone_code', 20);
            $table->timestamps();
        });

		Artisan::call('db:seed', [
            '--class' => CountriesSeeder::class,
			'--force' => true
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void{
        Schema::dropIfExists('countries');
    }
};
