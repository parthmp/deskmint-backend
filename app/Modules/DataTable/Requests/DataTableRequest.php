<?php

namespace App\Modules\DataTable\Requests;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class DataTableRequest extends FormRequest
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

		$default_per_page = null;

		$searched_term = $this->input('searched_term') ? (string) Sanitize::input((string)$this->input('searched_term')) : '';
		$current_page = $this->input('current_page') ? Sanitize::input($this->input('current_page')) : null;
		$sorted_column = $this->input('sorted_column') ? Sanitize::recursive($this->input('sorted_column')) : null;
		$default_per_page = $this->input('default_per_page') ? Sanitize::input($this->input('default_per_page')) : null;
		$date_range = $this->input('date_range') ? Sanitize::recursive($this->input('date_range')) : null;

		$per_page = $this->input('per_page') ? Sanitize::input($this->input('per_page')) : null;

		$this->merge([
			'company_id'			=>			$company_id,
			'searched_term'			=>			$searched_term,
			'current_page'			=>			$current_page,
			'sorted_column'			=>			$sorted_column,
			'default_per_page'		=>			$default_per_page,
			'date_range'			=>			$date_range,
			'per_page'				=>			$per_page
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
            'default_per_page'		=>	'required|integer|min:1',
            'per_page'				=>	'sometimes',
			'searched_term'			=>	'sometimes',
			'current_page'			=>	'sometimes',
			'sorted_column'			=>	'sometimes',
			'default_per_page'		=>	'sometimes',
			'date_range'			=>	'sometimes'
        ];
    }
}
