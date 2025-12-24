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

		$address_street = '';
		if($this->filled('address_street')){
			$address_street = Sanitize::input($this->input('address_street'));
		}

		$apt = '';
		if($this->filled('apt')){
			$apt = Sanitize::input($this->input('apt'));
		}

		$city = '';
		if($this->filled('city')){
			$city = Sanitize::input($this->input('city'));
		}

		$state = '';
		if($this->filled('state')){
			$state = Sanitize::input($this->input('state'));
		}

		$postal_code = '';
		if($this->filled('postal_code')){
			$postal_code = Sanitize::input($this->input('postal_code'));
		}

		$country_id = null;
		if($this->filled('country_id')){
			$country_id = Sanitize::input($this->input('country_id'));
		}

		$company_id = Sanitize::input($this->input('company_id'));

		$this->merge([
			'address_street'	=>	$address_street,
			'apt'				=>	$apt,
			'city'				=>	$city,
			'state'				=>	$state,
			'postal_code'		=>	$postal_code,
			'country_id'		=>	$country_id,
			'company_id'		=>	$company_id,
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
            'address_street'	=>	'sometimes',
            'apt'				=>	'sometimes',
            'city'				=>	'sometimes',
            'state'				=>	'sometimes',
            'postal_code'		=>	'sometimes',
            'country_id'		=>	'sometimes',
            'address_street'	=>	'sometimes'
        ];
    }
}
