<?php

namespace App\Modules\CustomFieldsFeature\Requests;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateCustomFieldsFeatureRequest extends FormRequest
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
		$input_field = Sanitize::input($this->input('input_field'));
		$label = Sanitize::input($this->input('label'));
		$is_required = Sanitize::input($this->input('is_required'));
		$add_edit_page_order = Sanitize::input($this->input('add_edit_page_order'));

		$past_label = $this->input('past_label') ? Sanitize::input($this->input('past_label')) : null;
		$select_options = $this->input('select_options') ? Sanitize::input($this->input('select_options')) : null;
		$placeholder = $this->input('placeholder') ? Sanitize::input($this->input('placeholder')) : null;
		$default_value = $this->input('default_value') ? Sanitize::input($this->input('default_value')) : null;

		$this->merge([
			'company_id'			=>		$company_id,
			'input_field'			=>		$input_field,
			'label'					=>		$label,
			'is_required'			=>		$is_required,
			'add_edit_page_order'	=>		$add_edit_page_order,
			'past_label'			=>		$past_label,
			'select_options'		=>		$select_options,
			'placeholder'			=>		$placeholder,
			'default_value'			=>		$default_value,
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
            'company_id'			=>		'required',
            'input_field'			=>		'required',
			'label'					=>		'required|regex:/^[a-zA-Z0-9 ]*$/',
			'is_required'			=>		'required',
			'add_edit_page_order'	=>		'required',
			'past_label'			=>		'sometimes',
			'select_options'		=>		'sometimes',
			'placeholder'			=>		'sometimes',
			'default_value'			=>		'sometimes'
        ];
    }

	public function messages()
	{
		return [
			'label' => 'Label must not contain special characters'
		];
	}
}
