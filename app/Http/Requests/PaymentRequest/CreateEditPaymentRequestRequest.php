<?php

namespace App\Http\Requests\PaymentRequest;

use App\Helpers\Sanitize;
use App\Modules\Payment\Enums\PaymentGateway;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class CreateEditPaymentRequestRequest extends FormRequest
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
		$client_id = Sanitize::input($this->input('client_id'));
		$amount = Sanitize::input($this->input('amount'));
		$label = Sanitize::input($this->input('label'));
		$payment_gateway = Sanitize::input($this->input('payment_gateway'));
		$send_reminders = $this->input('send_reminders');
		$send_request = $this->input('send_request');

		$this->merge([
			'company_id'		=>	$company_id,
			'client_id'			=>	$client_id,
			'amount'			=>	$amount,
			'label'				=>	$label,
			'payment_gateway'	=>	$payment_gateway,
			'send_reminders'	=>	$send_reminders,
			'send_request'		=>	$send_request
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
            'company_id'		=>	'required',
			'client_id'			=>	[
				'required', Rule::exists('clients', 'id')->where('company_id', $this->company_id),
			],
			'amount'			=>	'required|numeric|gt:0',
			'label'				=>	'required',
			'payment_gateway'	=>	['required', 'integer', Rule::in(PaymentGateway::cases())],
			'send_reminders'	=>	'required|boolean',
			'send_request'		=>	'required|boolean',
        ];
    }

	#[Override]
	public function messages()
	{
		return [
			'client_id.required' 			=> 'Invalid client provided',
			'client_id.exists' 				=> 'Invalid client provided',
			'amount.required' 				=> 'Invalid amount provided',
			'amount.gt' 					=> 'Amount must be greater than zero',
			'label.required' 				=> 'Please enter the label',
			'payment_gateway.required' 		=> 'Please select the payment gateway',
			'payment_gateway.integer' 		=> 'Please select the payment gateway',
			'payment_gateway.in' 			=> 'Invalid payment gateway provided',
			'send_reminders.required' 		=> 'Invalid send reminders value',
			'send_reminders.boolean' 		=> 'Invalid send reminders value',
			'send_request.required' 		=> 'Invalid send reminders value',
			'send_request.boolean' 			=> 'Invalid send reminders value'
		];
	}
}
