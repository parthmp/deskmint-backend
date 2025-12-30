<?php

namespace App\Http\Requests\InvoiceSettingsNumbers;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceSettingsNumbersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){
		
		$company_id = Sanitize::input($this->input('company_id'));

		$number_padding = Sanitize::input($this->input('number_padding'));
		$reset_counter = Sanitize::input($this->input('reset_counter'));

		$number_pattern = '';
		if($this->filled('number_pattern')){
			$number_pattern = Sanitize::input($this->input('number_pattern'));
		}

		$this->merge([
			'company_id'			=>		$company_id,
			'number_padding'		=>		$number_padding,
			'reset_counter'			=>		$reset_counter,
			'number_pattern'		=>		$number_pattern,
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
            'company_id'			=>	'required',
            'number_padding'		=>	'required',
			'reset_counter'			=>	'required',
			'number_pattern'		=>	'sometimes'
        ];
    }
}
