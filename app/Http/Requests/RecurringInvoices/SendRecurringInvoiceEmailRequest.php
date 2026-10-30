<?php

namespace App\Http\Requests\RecurringInvoices;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class SendRecurringInvoiceEmailRequest extends FormRequest
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
		$recurring_invoice_id = Sanitize::input($this->input('recurring_invoice_id'));

		$this->merge([
			'company_id'				=>		$company_id,
			'recurring_invoice_id'		=>		$recurring_invoice_id,
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
            'company_id'			=>	'required',
            'recurring_invoice_id'	=>	['required', Rule::exists('recurring_invoices', 'id')->where('id', $this->input('recurring_invoice_id'))],
        ];
    }

	/**
	 * messages function
	 *
	 * @return array
	 */
	public function messages(): array {

        return [
            'recurring_invoice_id.required'	=> 'Invalid id provided',
            'recurring_invoice_id.exists'	=> 'Invalid id provided'
        ];

    }
}
