<?php

namespace App\Http\Requests\CompanySettingsDefault;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateCompanySettingsDefaultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	/**
	 * prepareForValidation function
	 *
	 * @return void
	 */
	protected function prepareForValidation(){
		
		$invoice_terms = '';
		if($this->filled('invoice_terms')){
			$invoice_terms = Sanitize::input($this->input('invoice_terms'));
		}

		$invoice_footer = '';
		if($this->filled('invoice_footer')){
			$invoice_footer = Sanitize::input($this->input('invoice_footer'));
		}

		$company_id = Sanitize::input($this->input('company_id'));

		$this->merge([
			'invoice_terms'		=>	$invoice_terms,
			'invoice_footer'	=>	$invoice_footer,
			'company_id'		=>	$company_id
		]);

	}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id'		=>	'required',
            'invoice_terms'		=>	'sometimes',
            'invoice_footer'	=>	'sometimes',
        ];
    }
}
