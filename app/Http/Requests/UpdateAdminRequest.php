<?php

namespace App\Http\Requests;

use App\Helpers\Sanitize;
use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
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
			'name' => Sanitize::input($this->input('name')),
			'email' => Sanitize::input($this->input('email')),
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
    public function rules(): array {

		$id = $this->route('manage_admin');
		
       	$rules = [
            'name' 				=> 'required|string|max:255',
            'email' 			=>  [
                						'email',
                						'required',
                						Rule::unique('users', 'email')->ignore($id),
										'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
            						]
        ];

		if($this->filled('password') || $this->filled('confirm_password')){
			$rules['password'] = 'required|min:8|confirmed';
			$rules['confirm_password'] = 'required|same:password|min:8';
		}

		return $rules; 
    }

	/**
	 * messages function
	 *
	 * @return void
	 */
	public function messages(){
        return [
            'password.confirmed' 	=> 'Password and confirm password do not match',
        ];
    }
}
