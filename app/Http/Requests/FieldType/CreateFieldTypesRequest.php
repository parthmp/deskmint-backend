<?php

namespace App\Http\Requests\FieldType;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateFieldTypesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){

		$company_id = (int) Sanitize::input($this->input('company_id'));

		$input_type = Sanitize::input($this->input('input_type'));
		$input_name = Sanitize::input($this->input('input_name'));

		$this->merge([
			'company_id'		=>		$company_id,
			'input_type'		=>		$input_type,
			'input_name'		=>		$input_name,
		]);

	}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' 	=> 	'required',
            'input_type' 	=> 	'required',
			'input_name'	=>	'required'
        ];
    }
}
