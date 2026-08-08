<?php

namespace App\Http\Requests\EmailSettingsContent;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateEmailSettingsContentRequest extends FormRequest
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
	protected function prepareForValidation(){

		$company_id = (int) Sanitize::input($this->input('company_id'));

		$email_content_invoice = '';

		if($this->filled('email_content_invoice')){
			$email_content_invoice = Sanitize::input($this->input('email_content_invoice'));
		}

		$email_content_reminder = '';

		if($this->filled('email_content_reminder')){
			$email_content_reminder = Sanitize::input($this->input('email_content_reminder'));
		}

		$email_content_payment_request = '';

		if($this->filled('email_content_payment_request')){
			$email_content_payment_request = Sanitize::input($this->input('email_content_payment_request'));
		}

		$email_content_reminder_payment_request = '';
		if($this->filled('email_content_reminder_payment_request')){
			$email_content_reminder_payment_request = Sanitize::input($this->input('email_content_reminder_payment_request'));
		}
		
		$this->merge([
			'company_id'							=>	$company_id,
			'email_content_invoice'					=>	$email_content_invoice,
			'email_content_reminder'				=>	$email_content_reminder,
			'email_content_payment_request'			=>	$email_content_payment_request,
			'email_content_reminder_payment_request'=>	$email_content_reminder_payment_request
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
            'company_id'							=>	'required',
            'email_content_invoice'					=>	'sometimes',
            'email_content_reminder'				=>	'sometimes',
            'email_content_payment_request'			=>	'sometimes',
            'email_content_reminder_payment_request'=>	'sometimes'
        ];
    }
}
