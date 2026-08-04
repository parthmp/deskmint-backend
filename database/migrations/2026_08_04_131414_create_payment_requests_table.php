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
        Schema::create('payment_requests', function (Blueprint $table) {

            $table->id();
			$table->string('uuid', 255)->unique();

			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->unsignedBigInteger('client_id');
			$table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

			$table->unsignedBigInteger('transaction_id')->nullable();
			$table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
			
			$table->string('label', 255)->default('');
			$table->decimal('amount', 10, 2)->default(0);
			$table->tinyInteger('status');
			$table->tinyInteger('payment_gateway');
			$table->tinyInteger('send_reminders')->default(0);
			$table->integer('reminders_sent')->default(0);
			$table->datetime('last_reminder_sent_at');
			
			$table->softDeletes();
            $table->timestamps();
			

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
