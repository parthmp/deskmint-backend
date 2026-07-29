<?php

namespace App\Http\Requests\LoginSettings;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class LoginSettingsShowRequest extends FormRequest
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

		$type = Sanitize::input($this->input('type'));
		$company_id = Sanitize::input($this->input('company_id'));
		
		$this->merge([
			'type'				=>	$type,
			'company_id'		=>	$company_id
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
            'company_id'		=>	'required',
            'type'				=>	'required|in:local,global'
        ];
    }
}
