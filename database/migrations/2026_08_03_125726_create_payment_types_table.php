<?php

use Database\Seeders\PaymentTypeSeeder;
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
        Schema::create('payment_types', function (Blueprint $table) {
            
			$table->id();

			$table->string('payment_type', 255);

			$table->softDeletes();
            $table->timestamps();
			
        });
		
		Artisan::call('db:seed', [
			'--class' => PaymentTypeSeeder::class,
			'--force' => true
		]);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_types');
    }
};
