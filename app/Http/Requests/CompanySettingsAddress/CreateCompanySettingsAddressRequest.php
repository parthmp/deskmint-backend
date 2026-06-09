<?php

namespace App\Http\Requests\CompanySettingsAddress;

use App\Helpers\Sanitize;
use Illuminate\Foundation\Http\FormRequest;

class CreateCompanySettingsAddressRequest extends FormRequest
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


		$address_street = Sanitize::input($this->input('address_street'));
		$apt = Sanitize::input($this->input('apt'));
		$city = Sanitize::input($this->input('city'));
		$state = Sanitize::input($this->input('state'));
		$postal_code = Sanitize::input($this->input('postal_code'));
		$country_id = Sanitize::input($this->input('country_id'));
		$company_id = Sanitize::input($this->input('company_id'));
		$identifier = Sanitize::input($this->input('identifier') ?? '');
		$scheme = Sanitize::input($this->input('scheme') ?? '');
		

		$this->merge([
			'address_street'	=>	$address_street,
			'apt'				=>	$apt,
			'city'				=>	$city,
			'state'				=>	$state,
			'postal_code'		=>	$postal_code,
			'country_id'		=>	$country_id,
			'company_id'		=>	$company_id,
			'identifier'		=>	$identifier,
			'scheme'			=>	$scheme,
		]);
	}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules() : array {
        return [
            'company_id'		=>	'required',
            'address_street'	=>	'required',
            'apt'				=>	'required',
            'city'				=>	'required',
            'state'				=>	'required',
            'postal_code'		=>	'required',
            'country_id'		=>	'required',
            'identifier'		=>	'sometimes',
            'scheme'			=>	'sometimes',
        ];
    }
}
