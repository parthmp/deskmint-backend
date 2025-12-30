<?php

namespace App\Http\Requests;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateEmailSettingsSMTPRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	/**
	 * prepareForValidation function
	 *
	 * @return void
	 */
	protected function prepareForValidation() : void {

		$company_id = (int) Sanitize::input($this->input('company_id'));

		$host = Sanitize::input($this->input('host'));
		$port = Sanitize::input($this->input('port'));
		$username = Sanitize::input($this->input('username'));
		$mail_from_address = Sanitize::input($this->input('mail_from_address'));
		$mail_from_name = Sanitize::input($this->input('mail_from_name'));
		$reply_to_address = Sanitize::input($this->input('reply_to_address'));
		$encryption = Sanitize::input($this->input('encryption'));
		$test_email_address = Sanitize::input($this->input('test_email_address'));
		
		$password = '';

		if($this->filled('password')){
			$password = $this->input('password');
		}

		$this->merge([
			'company_id'			=>		$company_id,
			'host'					=>		$host,
			'port'					=>		$port,
			'username'				=>		$username,
			'mail_from_address'		=>		$mail_from_address,
			'mail_from_name'		=>		$mail_from_name,
			'reply_to_address'		=>		$reply_to_address,
			'encryption'			=>		$encryption,
			'test_email_address'	=>		$test_email_address,
			'password'				=>		$password
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
           	'company_id'			=>		'required',
           	'host'					=>		'required',
			'port'					=>		'required',
			'username'				=>		'required',
			'mail_from_address'		=>		'required|email',
			'mail_from_name'		=>		'required',
			'reply_to_address'		=>		'required|email',
			'encryption'			=>		'required|in:tls,ssl',
			'test_email_address'	=>		'required|email',
			'password'				=>		'sometimes'
        ];
    }
}
