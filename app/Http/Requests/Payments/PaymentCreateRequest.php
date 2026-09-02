<?php

namespace App\Http\Requests\Payments;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class PaymentCreateRequest extends FormRequest
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
		$payment_type = Sanitize::input($this->input('payment_type'));
		$payment_number = Sanitize::input($this->input('payment_number'));

		$this->merge([
			'company_id'		=>	$company_id,
			'client_id'			=>	$client_id,
			'amount'			=>	$amount,
			'payment_type'		=>	$payment_type,
			'payment_number'	=>	$payment_number
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
			'client_id'			=>	['required', Rule::exists('clients', 'id')->where('company_id', $this->input('company_id'))],
			'payment_type'		=>	['required', Rule::exists('payment_types', 'id')],
			'amount'			=>	'required|numeric|gt:0|lt:999999999',
			'payment_number'	=>	'required'
        ];
    }

	public function messages(): array {

        return [
            'client_id.required'	=> 'Please select the client',
            'payment_type.required'	=> 'Please select payment type',
            'payment_type.exists'	=> 'Please select payment type',
            'client_id.exists' 		=> 'Client does not exist',
            'amount.required' 		=> 'Please enter the amount',
            'amount.numeric' 		=> 'Please enter the amount',
            'amount.gt' 			=> 'Amount must be greater than zero',
            'amount.lt' 			=> 'Amount must be lesser than 999999999',
        ];

    }
}
