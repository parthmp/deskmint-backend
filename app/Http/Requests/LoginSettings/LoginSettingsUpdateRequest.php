<?php

namespace App\Http\Requests\LoginSettings;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class LoginSettingsUpdateRequest extends FormRequest
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
		$type = Sanitize::input($this->input('type'));
		$login_limits_attempts = Sanitize::input($this->input('login_limits_attempts'));
		$login_limits_minutes = Sanitize::input($this->input('login_limits_minutes'));

		$this->merge([
			'company_id'			=>	$company_id,
			'type'					=>	$type,
			'login_limits_flag'		=>	(bool) $this->input('login_limits_flag'),
			'two_factor_auth_flag'	=>	(bool) $this->input('two_factor_auth_flag'),
			'login_email_flag'		=>	(bool) $this->input('login_email_flag'),
			'login_limits_attempts'	=>	$login_limits_attempts,
			'login_limits_minutes'	=>	$login_limits_minutes
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
            'company_id'			=>	'required',
            'login_limits_flag'		=>	'required|boolean',
			'two_factor_auth_flag'	=>	'required|boolean',
			'login_email_flag'		=>	'required|boolean',
			'login_limits_attempts'	=>	'required|integer|gt:1',
			'login_limits_minutes'	=>	'required|integer|gt:1',
			'type'					=>	'required|in:local,global'
        ];
    }
}
