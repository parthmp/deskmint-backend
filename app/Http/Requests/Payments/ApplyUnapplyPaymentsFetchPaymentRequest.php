<?php

namespace App\Http\Requests\Payments;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ApplyUnapplyPaymentsFetchPaymentRequest extends FormRequest
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
		$payment_id = Sanitize::input($this->input('payment_id'));

		$this->merge([
			'company_id'		=>	$company_id,
			'payment_id'		=>	$payment_id
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
			'payment_id'		=>	['required', Rule::exists('payments', 'id')->where('company_id', $this->input('company_id'))],
        ];
    }

	public function messages(): array {

        return [
            'payment_id.required'	=> 'Invalid payment selected',
            'payment_id.exists'		=> 'Invalid payment selected'
        ];

    }
}
