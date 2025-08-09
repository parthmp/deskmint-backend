<?php

namespace Database\Factories;

use App\Models\ClientsCustomField;
use App\Models\Company;
use App\Models\CustomFieldType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClientsCustomField>
 */
class ClientsCustomFieldFactory extends Factory{


	private function setTypeParams(ClientsCustomField $model){

		if(!empty($model->type_params)){
        	return;
		}

		if(strtolower((string) $model->customFieldType->input_type) === 'select'){
			$model->type_params = implode(', ', $this->faker->words($this->faker->numberBetween(2, 10)));
		}

	}

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array{

        return [
            'custom_field_type_id'					=>			CustomFieldType::factory(),
            'company_id'							=>			Company::factory(),
            'label'									=>			$this->faker->text(10),
            'placeholder'							=>			'PL '.$this->faker->text(12),
            'required'								=>			$this->faker->boolean(),
            'type_params'							=>			'',
            'default_value'							=>			$this->faker->text(5),
            'order_on_add_edit_page'				=>			$this->faker->numberBetween(1, 100),
            'order_column_on_index_page'			=>			$this->faker->numberBetween(1, 100),
            'show_on_index_page'					=>			$this->faker->boolean()
        ];
    }

	public function configure(){
        return $this->afterMaking(function (ClientsCustomField $model){
            $this->setTypeParams($model);
        })->afterCreating(function (ClientsCustomField $model){
			$this->setTypeParams($model);
            $model->save();
        });
    }
}
