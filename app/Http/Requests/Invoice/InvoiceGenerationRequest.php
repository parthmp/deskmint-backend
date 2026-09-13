<?php

namespace App\Http\Requests\Invoice;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class InvoiceGenerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	#[Override]
	protected function prepareForValidation()
	{
		$invoice_id = (int) Sanitize::input($this->input('invoice_id'));
		$company_id = (int) Sanitize::input($this->input('company_id'));
		$time_offset_minutes = (int) Sanitize::input($this->input('time_offset_minutes'));
		$send_invoice = false;

		if($this->has('send_invoice')){
			$send_invoice = filter_var(Sanitize::input($this->input('send_invoice')), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
			logger($send_invoice);
		}

		$this->merge([
			'invoice_id'			=>	$invoice_id,
			'company_id'			=>	$company_id,
			'send_invoice'			=>	$send_invoice,
			'time_offset_minutes'	=>	$time_offset_minutes
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
            'invoice_id'			=>	'required|numeric|exists:invoices,id',
            'company_id'			=>	'required|numeric',
            'send_invoice'			=>	'sometimes',
            'time_offset_minutes'	=>	'required|numeric'
        ];
    }
}
