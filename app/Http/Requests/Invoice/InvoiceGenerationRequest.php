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

		$this->merge([
			'invoice_id'			=>	$invoice_id,
			'company_id'			=>	$company_id,
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
            'invoice_id'			=>	'required|numeric',
            'company_id'			=>	'required|numeric',
            'time_offset_minutes'	=>	'required|numeric'
        ];
    }
}
