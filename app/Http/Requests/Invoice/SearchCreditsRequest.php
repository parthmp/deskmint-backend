<?php

namespace App\Http\Requests\Invoice;

use App\Helpers\Sanitize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class SearchCreditsRequest extends FormRequest
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
		
		$company_id = Sanitize::input($this->input('company_id'));
		$searched = Sanitize::input($this->input('searched'));
		$invoice_id = Sanitize::input($this->input('invoice_id'));
		$applied_ids = Sanitize::recursive($this->input('applied_ids'));
		$fetched_and_removed_ids = Sanitize::recursive($this->input('fetched_and_removed_ids'));

		$this->merge([
			'searched'					=>	$searched,
			'invoice_id'				=>	$invoice_id,
			'applied_ids'				=>	$applied_ids,
			'fetched_and_removed_ids'	=>	$fetched_and_removed_ids,
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
            'searched'					=>	'sometimes',
            'invoice_id'				=>	['required', Rule::exists('invoices', 'id')->where('company_id', $this->input('company_id'))],
            'applied_ids'				=>	'sometimes',
            'fetched_and_removed_ids'	=>	'sometimes',
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
