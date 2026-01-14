<?php

namespace App\Http\Requests\ForgotPassword;
use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class SendResetPasswordCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){

		$email_address = Sanitize::input($this->input('email_address'));
		$turnstile_token = $this->input('turnstile_token');
		$device = Sanitize::input($this->input('device'));

		$this->merge([
			'email_address'		=>	$email_address,
			'turnstile_token'	=>	$turnstile_token,
			'device'			=>	$device,
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
            'email_address'		=>		'required|email',
			'turnstile_token'	=>		'required',
			'device'			=>		'required'
        ];
    }
}
