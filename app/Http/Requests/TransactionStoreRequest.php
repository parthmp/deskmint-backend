<?php

namespace App\Http\Requests;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class TransactionStoreRequest extends FormRequest
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

		$company_id = (int) Sanitize::input($this->input('company_id'));
		$invoice_id = (int) Sanitize::input($this->input('invoice_id'));
		$amount = Sanitize::input($this->input('amount'));
		$gateway_fees = Sanitize::input($this->input('gateway_fees'));
		$received_amount = Sanitize::input($this->input('received_amount'));
		$payment_method = Sanitize::input($this->input('payment_method'));

		$this->merge([
			'company_id'		=>	$company_id,
			'invoice_id'		=>	$invoice_id,
			'amount'			=>	$amount,
			'gateway_fees'		=>	$gateway_fees,
			'received_amount'	=>	$received_amount,
			'payment_method'	=>	$payment_method
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
            'company_id'		=>	'required|numeric',
            'invoice_id'		=>	'required|numeric',
			'amount'			=>	'required|numeric',
			'gateway_fees'		=>	'required|numeric',
			'received_amount'	=>	'required|numeric',
			'payment_method'	=>	['required', 'numeric', Rule::in([PAYMENT_CASH, PAYMENT_NETBANKING])],
        ];
    }

	public function messages(): array {
		return [
			'payment_method.in' 		=> 'Invalid payment method provided.'
		];
	}
}
