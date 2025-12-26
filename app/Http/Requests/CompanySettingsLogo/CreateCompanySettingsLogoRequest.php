<?php

namespace App\Http\Requests\CompanySettingsLogo;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateCompanySettingsLogoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation()
	{
		$this->merge([
			'company_id'	=>	(int) Sanitize::input($this->input('company_id'))
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
            'logo'			=>	'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120'
        ];
    }

	public function messages()
	{
		return [
			'logo.required'	=>	'Unable to upload - Invalid file'
		];
	}
}
