<?php

namespace App\Http\Requests\PaymentRequest;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class MarkPaymentRequestCompletedRequest extends FormRequest
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
		
		$create_payment = $this->input('create_payment');
		$payment_type = Sanitize::input($this->input('payment_type'));

		$this->merge([
			'company_id'		=>	$company_id,
			'create_payment'	=>	$create_payment,
			'payment_type'		=>	$payment_type
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
            'create_payment'	=>	'required|boolean',
            'payment_type'		=>	'sometimes',
        ];
    }

	#[Override]
	public function messages()
	{
		return [
			'create_payment.required' 		=> 'Invalid payment creation value',
			'create_payment.boolean' 		=> 'Invalid payment creation value'
		];
	}
}
