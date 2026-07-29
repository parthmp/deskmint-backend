<?php

namespace App\Http\Requests\Admin;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(): void {

		$company_id = Sanitize::input($this->input('company_id'));
		$login_limits_attempts = Sanitize::input($this->input('login_limits_attempts'));
		$login_limits_minutes = Sanitize::input($this->input('login_limits_minutes'));
		
        $this->merge([
            'company_id' 			=> 	$company_id,
            'name' 					=> 	Sanitize::input((string) $this->input('name')),
            'email' 				=> 	Sanitize::input((string) $this->input('email')),
			'login_limits_flag'		=>	(bool) $this->input('login_limits_flag'),
			'two_factor_auth_flag'	=>	(bool) $this->input('two_factor_auth_flag'),
			'login_email_flag'		=>	(bool) $this->input('login_email_flag'),
			'login_limits_attempts'	=>	$login_limits_attempts,
			'login_limits_minutes'	=>	$login_limits_minutes
        ]);

        if($this->has('email')){
            $this->merge([
                'email' => trim(strtolower($this->input('email')))
            ]);
        }
        
        if($this->has('confirm_password')){
            $this->merge([
                'password_confirmation' => $this->input('confirm_password')
            ]);
        }

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
       	return [
            'name' 					=> 'required|string|max:255',
            'email' 				=> 'required|email|unique:users,email|regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
            'password' 				=> 'required|min:8|confirmed',
			'company_id'			=>	'required',
            'login_limits_flag'		=>	'required|boolean',
			'two_factor_auth_flag'	=>	'required|boolean',
			'login_email_flag'		=>	'required|boolean',
			'login_limits_attempts'	=>	'required|integer|gt:1',
			'login_limits_minutes'	=>	'required|integer|gt:1'
        ];
    }

	public function messages(){
        return [
        	'password.confirmed' => 'Password and confirm password do not match',
        ];
    }
}
