<?php

namespace App\Http\Requests\Invoice;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class AddCreditOrPaymentRequest extends FormRequest
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
		$invoice_id = Sanitize::input($this->input('invoice_id'));
		$amount = Sanitize::input($this->input('amount'));
		$type = Sanitize::input($this->input('type'));
		$payment_type = Sanitize::input($this->input('payment_type'));
		$uuid = Sanitize::input($this->input('uuid'));

		$this->merge([
			'company_id'			=>	$company_id,
			'invoice_id'			=>	$invoice_id,
			'amount'				=>	$amount,
			'type'					=>	$type,
			'payment_type'			=>	$payment_type,
			'uuid'					=>	$uuid
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
			'invoice_id'	=> 	['required', Rule::exists('invoices', 'id')->where('company_id', $this->input('company_id'))],
			'amount'		=>	'required|numeric|gt:0',
			'type'			=>	['required', Rule::in(['credit', 'payment'])],
			'payment_type'	=>	'sometimes',
			'uuid'			=>	'required'
        ];
    }
}
