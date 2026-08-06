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
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dateTime('hidden_sent_at')->after('last_reminder_sent_at');
            $table->dateTime('sent_at')->nullable()->after('hidden_sent_at');
			$table->dateTime('last_reminder_sent_at')->nullable(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropColumn('hidden_sent_at');
            $table->dropColumn('sent_at');
			$table->dateTime('last_reminder_sent_at')->nullable(false)->change();
        });
    }
};
