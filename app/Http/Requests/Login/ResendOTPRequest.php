<?php

namespace App\Http\Requests\Login;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class ResendOTPRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){
		
		$token = Sanitize::input($this->input('token'));
		$device = Sanitize::input($this->input('device'));

		$this->merge([
			'token'		=>		$token,
			'device'	=>		$device
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
            'token'		=>	'required',
			'device'	=> 	'required'
        ];
    }
}
