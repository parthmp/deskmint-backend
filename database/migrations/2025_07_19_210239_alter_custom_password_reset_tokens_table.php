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
        // Rename the column first (before renaming the table)
        Schema::table('custom_password_reset_tokens', function (Blueprint $table) {
            $table->renameColumn('reset_token', 'reset_code');
        });

        // Rename the table
        Schema::rename('custom_password_reset_tokens', 'custom_password_resets');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename the table back
        Schema::rename('custom_password_resets', 'custom_password_reset_tokens');

        // Rename the column back
        Schema::table('custom_password_reset_tokens', function (Blueprint $table) {
            $table->renameColumn('reset_code', 'reset_token');
        });
    }
};
