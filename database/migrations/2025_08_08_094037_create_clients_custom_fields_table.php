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
        Schema::create('clients_custom_fields', function (Blueprint $table){
            $table->id();
			$table->unsignedBigInteger('custom_field_type_id');
			$table->foreign('custom_field_type_id')->references('id')->on('custom_field_types')->onDelete('cascade');
			$table->unsignedBigInteger('company_id');
			$table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
			$table->string('label', 20);
			$table->string('placeholder', 20)->default('');
			$table->tinyInteger('required')->default(0);
			$table->text('type_params');
			$table->string('default_value', 255)->default('');
			$table->smallInteger('order_on_add_edit_page')->default(0);
			$table->smallInteger('order_column_on_index_page')->default(0);
			$table->tinyInteger('show_on_index_page')->default(1);
			$table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients_custom_fields');
    }
};
