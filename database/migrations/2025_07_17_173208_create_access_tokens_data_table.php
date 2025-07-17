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
        Schema::create('access_tokens_data', function (Blueprint $table) {
            $table->id();
			$table->unsignedBigInteger('token_id');
			$table->foreign('token_id')->references('id')->on('personal_access_tokens')->onDelete('cascade');
			$table->string('device', 255);
			$table->string('user_agent')->nullable();
    		$table->string('ip_address', 20)->nullable();
			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_tokens_data');
    }
};
