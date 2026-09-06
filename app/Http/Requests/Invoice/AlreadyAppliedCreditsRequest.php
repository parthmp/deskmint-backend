<?php

namespace App\Http\Requests\Invoice;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class AlreadyAppliedCreditsRequest extends FormRequest
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
		
		$this->merge([
			'company_id'	=>	$company_id,
			'invoice_id'	=>	$invoice_id,
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
            'company_id'				=>	'required',
            'invoice_id'				=>	['required', Rule::exists('invoices', 'id')->where('company_id', $this->input('company_id'))],
        ];
    }

	#[Override]
	public function messages()
	{
		return [
			'invoice_id.required'	=>	'Invalid invoice selected',
			'invoice_id.rule'		=>	'Invalid invoice selected'
		];
	}
}
