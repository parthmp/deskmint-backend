<?php

namespace App\Http\Requests\PaymentType;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class PaymentTypeCreateEditRequest extends FormRequest
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

		$payment_type = Sanitize::input($this->input('payment_type'));

		$this->merge([
			'company_id'	=>	$company_id,
			'payment_type'	=>	$payment_type
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
            'company_id'	=>	'required',
            'payment_type'	=>	'required',
        ];
    }

	#[Override]
	public function messages()
	{
		return [
			'payment_type'	=>	'Please enter payment type'
		];
	}
}
