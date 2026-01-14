<?php

namespace App\Http\Requests\ForgotPassword;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){

		$reset_code = Sanitize::input($this->input('reset_code'));
		$device = Sanitize::input($this->input('device'));
		$password = $this->input('password');
		$retype_password = $this->input('retype_password');

		$this->merge([
			'reset_code'		=>		$reset_code,
			'password'			=>		$password,
			'retype_password'	=>		$retype_password,
			'device'			=>		$device
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
            'reset_code'		=>		'required',
			'password'			=>		'required|min:8',
			'retype_password'	=>		'required|min:8',
			'device'			=>		'required'
        ];
    }
}
