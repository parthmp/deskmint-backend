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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
			$table->tinyInteger('login_limits_flag');
			$table->integer('login_limits_attempts');
			$table->integer('login_limits_minutes');
			$table->tinyInteger('two_factor_auth_flag');
			$table->tinyInteger('login_email_flag');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
