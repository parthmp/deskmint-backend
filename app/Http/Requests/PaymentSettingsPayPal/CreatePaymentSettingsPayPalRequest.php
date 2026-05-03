<?php

namespace App\Http\Requests\PaymentSettingsPayPal;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentSettingsPayPalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){
		
		$company_id = (int) Sanitize::input($this->input('company_id'));
		$client_id = Sanitize::input($this->input('client_id'));
		$app_id = Sanitize::input($this->input('app_id'));
		$webhook_id = Sanitize::input($this->input('webhook_id'));
		$secret = Sanitize::input($this->input('secret'));
		$mode = Sanitize::input($this->input('mode'));

		$this->merge([
			'company_id'	=>		$company_id,
			'client_id'		=>		$client_id,
			'app_id'		=>		$app_id,
			'webhook_id'	=>		$webhook_id,
			'secret'		=>		$secret,
			'mode'			=>		$mode
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
            'company_id'	=>	'required',
            'client_id'		=>	'required',
            'app_id'		=>	'required',
            'webhook_id'	=>	'required',
			'secret'		=>	'required',
			'mode'			=>	'required|in:sandbox,live',
        ];
    }
}
