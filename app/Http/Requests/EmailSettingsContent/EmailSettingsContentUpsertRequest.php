<?php

namespace App\Http\Requests\EmailSettingsContent;

use App\Enums\EmailSettings\EmailSettingsContent;
use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class EmailSettingsContentUpsertRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	#[Override]
	protected function prepareForValidation(){

		$company_id = Sanitize::input($this->input('company_id'));
		$key = Sanitize::input($this->input('key'));
		$data = $this->input('data');

		$this->merge([
			'company_id'	=>	$company_id,
			'key'			=>	$key,
			'data'			=>	$data
		]);

	}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id'	=>	'required',
			'key'			=>	['required', Rule::in(EmailSettingsContent::getAllValues())],
			'data'			=>	'sometimes'
        ];
    }

	public function messages(): array {

        return [
            'key.required'			=> 'Invalid request',
            'key.in' 				=> 'Invalid request'
        ];

    }
}
