<?php

namespace App\Http\Requests\CompanySettingsDetails;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateCompanySettingsDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

	protected function prepareForValidation(){

		$company_id = Sanitize::input($this->input('company_id'));
		$company_name = Sanitize::input($this->input('company_name'));
		$company_identifier = Sanitize::input($this->input('company_identifier') ?? '');
		$scheme = Sanitize::input($this->input('scheme') ?? '');

		$size = '';
		if($this->filled('size')){
			$size = Sanitize::input($this->input('size'));
		}
		
		$id_number = '';
		if($this->filled('id_number')){
			$id_number = Sanitize::input($this->input('id_number'));
		}

		$gst = '';
		if($this->filled('gst')){
			$gst = Sanitize::input($this->input('gst'));
		}

		$classification = '';
		if($this->filled('classification')){
			$classification = Sanitize::input($this->input('classification'));
		}

		$website = '';
		if($this->filled('website')){
			$website = Sanitize::input($this->input('website'));
		}

		$email = '';
		if($this->filled('email')){
			$email = Sanitize::input($this->input('email'));
		}

		$phone = '';
		if($this->filled('phone')){
			$phone = Sanitize::input($this->input('phone'));
		}

		$this->merge([
			'company_id'			=>	$company_id,
			'company_name'			=>	$company_name,
			'company_identifier'	=>	$company_identifier,
			'scheme'				=>	$scheme,
			'size'					=>	$size,
			'id_number'				=>	$id_number,
			'gst'					=>	$gst,
			'classification'		=>	$classification,
			'website'				=>	$website,
			'email'					=>	$email,
			'phone'					=>	$phone
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
			'company_id'			=>	'required',
            'company_name'			=>	'required',
            'size'					=>	'sometimes',
            'id_number'				=>	'sometimes',
            'gst'					=>	'sometimes',
            'classification'		=>	'sometimes',
            'website'				=>	'sometimes',
            'email'					=>	'sometimes',
            'phone'					=>	'sometimes',
            'company_identifier'	=>	'sometimes',
            'scheme'				=>	'sometimes'
        ];
    }
}
