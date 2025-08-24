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
        Schema::create('settings_index_columns', function (Blueprint $table) {
            
			$table->id();
			
			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

			$table->string('feature_name', 100);
			$table->text('columns_json');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings_index_columns');
    }
};
