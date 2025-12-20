<?php

namespace App\Http\Requests;

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
		
        $this->merge([
            'name' 	=> Sanitize::input((string) $this->input('name')),
            'email' => Sanitize::input((string) $this->input('email'))
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
            'password' 				=> 'required|min:8|confirmed'
        ];
    }

	public function messages(){
        return [
        	'password.confirmed' => 'Password and confirm password do not match',
        ];
    }
}
