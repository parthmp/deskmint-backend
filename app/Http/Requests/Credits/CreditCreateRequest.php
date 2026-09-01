<?php

namespace App\Http\Requests\Credits;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class CreditCreateRequest extends FormRequest
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
		$credit_number = Sanitize::input($this->input('credit_number'));

		$this->merge([
			'company_id'	=>	$company_id,
			'client_id'		=>	$client_id,
			'amount'		=>	$amount,
			'credit_number'	=>	$credit_number
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
			'client_id'		=>	['required', Rule::exists('clients', 'id')->where('company_id', $this->input('company_id'))],
			'amount'		=>	'required|numeric|gt:0|lt:999999999',
			'credit_number'	=>	'required'
        ];
    }

	public function messages(): array {

        return [
            'client_id.required'	=> 'Please select the client',
            'client_id.exists' 		=> 'Client does not exist',
            'amount.required' 		=> 'Please enter the amount',
            'amount.numeric' 		=> 'Please enter the amount',
            'amount.gt' 			=> 'Amount must be greater than zero',
            'amount.lt' 			=> 'Amount must be lesser than 999999999',
        ];

    }
}
