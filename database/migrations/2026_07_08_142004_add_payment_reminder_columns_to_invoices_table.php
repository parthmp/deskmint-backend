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
            $table->integer('reminders_sent')->default(0)->after('settings_snapshot');
			$table->dateTime('last_reminder_sent_at')->nullable()->after('reminders_sent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('reminders_sent');
            $table->dropColumn('last_reminder_sent_at');
        });
    }
};
