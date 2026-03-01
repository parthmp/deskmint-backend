<?php

namespace App\Http\Requests\Product;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class SearchProductRequest extends FormRequest
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
		$searched = (string) Sanitize::input($this->input('searched'));

		$this->merge([
			'company_id'	=>		$company_id,
			'searched'		=>		$searched
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
            'company_id'	=>	'required',
            'searched'		=>	'required'
        ];
    }
}
