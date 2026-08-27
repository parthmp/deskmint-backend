<?php

namespace App\Http\Requests\Payments;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ApplyUnapplyPaymentsSearchRequest extends FormRequest
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
		$searched = Sanitize::input($this->input('searched'));
		$payment_id = Sanitize::input($this->input('payment_id'));
		$applied_ids = Sanitize::recursive($this->input('applied_ids'));
		$paid_ids = Sanitize::recursive($this->input('fetched_and_removed_ids'));

		$this->merge([
			'company_id'		=>	$company_id,
			'searched'			=>	$searched,
			'payment_id'			=>	$payment_id,
			'applied_ids'		=>	$applied_ids,
			'paid_ids'			=>	$paid_ids
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
			'searched'		=>	'sometimes',
			'applied_ids'	=>	'sometimes',
			'paid_ids'		=>	'sometimes',
			'payment_id'	=>	['required', Rule::exists('payments', 'id')->where('company_id', $this->input('company_id'))],
        ];
    }

	public function messages(): array {

        return [
            'payment_id.required'	=> 'Invalid payment selected',
            'payment_id.exists'		=> 'Invalid payment selected'
        ];

    }
}
