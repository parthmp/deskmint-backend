<?php

namespace App\Http\Requests\InvoiceSettingsGeneral;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateInvoiceSettingsGeneralRequest extends FormRequest
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

		$company_id = (int) Sanitize::input($this->input('company_id'));

		$template = Sanitize::input($this->input('template'));

		$font_size = (int) Sanitize::input($this->input('font_size'));
		$logo_size = (int) Sanitize::input($this->input('logo_size'));
		$primary_color = Sanitize::input($this->input('primary_color'));
		$secondary_color = Sanitize::input($this->input('secondary_color'));

		$e_invoice = $this->boolean('e_invoice');

		$this->merge([
			'company_id'			=>	$company_id,
			'template'				=>	$template,
			'font_size'				=>	$font_size,
			'logo_size'				=>	$logo_size,
			'primary_color'			=>	$primary_color,
			'secondary_color'		=>	$secondary_color,
			'e_invoice'				=>	$e_invoice
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
            'template'			=>	'required',
			'font_size'			=>	'required|numeric',
			'logo_size'			=>	'required|numeric',
			'primary_color'		=>	'required|hex_color',
			'secondary_color'	=>	'required|hex_color',
			'e_invoice'			=>	'sometimes'
        ];
    }
}
