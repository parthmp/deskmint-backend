<?php

use App\Models\Setting;
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
		Schema::table('settings', function (Blueprint $table) {
			
			$table->unsignedBigInteger('company_id')->nullable()->after('id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->unsignedBigInteger('user_id')->nullable()->after('company_id');
			$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

			$setting = new Setting();
			$setting->id = 1;
			$setting->login_limits_flag = 0;
			$setting->login_limits_attempts = 3;
			$setting->login_limits_minutes = 10;
			$setting->two_factor_auth_flag = 0;
			$setting->login_email_flag = 0;
			$setting->save();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {

			$table->dropForeign(['company_id']);
			$table->dropColumn('company_id');

			$table->dropForeign(['user_id']);
			$table->dropColumn('user_id');

			Setting::where('id', '=', 1)->delete();

        });
    }
};
