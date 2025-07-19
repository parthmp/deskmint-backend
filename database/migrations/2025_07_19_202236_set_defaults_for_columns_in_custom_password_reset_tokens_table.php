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
        Schema::table('custom_password_reset_tokens', function (Blueprint $table) {
            $table->tinyInteger('used')->default(0)->change();
            $table->timestamp('used_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_password_reset_tokens', function (Blueprint $table) {
            $table->boolean('used')->default(false)->change();
        	$table->timestamp('used_at')->nullable(false)->change();
        });
    }
};
