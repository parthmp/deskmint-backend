<?php

namespace App\Http\Requests;

use App\Helpers\Sanitize;
use App\Modules\Payment\Enums\InvoiceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ToggleCancelRequest extends FormRequest
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
		$company_id = (int) Sanitize::input($this->input('company_id'));
		$invoice_id = (int) Sanitize::input($this->input('invoice_id'));
		$status = (int) Sanitize::input($this->input('status'));

		$this->merge([
			'company_id'	=>		$company_id,
			'invoice_id'	=>		$invoice_id,
			'status'		=>		$status
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
            'company_id'	=>	'required|numeric',
            'invoice_id'	=>	[
								'required',
								'numeric',
								Rule::exists('invoices', 'id')->where('company_id', $this->company_id),
							],
            'status'		=> ['required', 'numeric', Rule::in([InvoiceStatus::DRAFT->value, InvoiceStatus::SENT->value, InvoiceStatus::CANCELLED->value])],
        ];
    }

	public function messages(): array {
		return [
			'invoice_id.exists' => 'The selected invoice does not exist.',
			'status.in' 		=> 'Invalid status provided.',
		];
	}
}
