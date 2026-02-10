<?php

namespace App\Services\Client;

use App\Models\ClientsCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Services\Client\Exceptions\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * ClientValidationService class
 */
class ClientValidationService{

	public function __construct(private CustomFields $custom_fields){}

	/**
	 * validateForIndex function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateForIndex(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'default_per_page'	=>	'required|integer|min:1'
		]);

		return !$v->fails();

	}

	/**
	 * validatePersonInfo function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validatePersonInfo(Request $request) : bool {

		$validation_rules1 = [
			'personal_info.first_name.value'	=>	'required',
			'personal_info.last_name.value'		=>	'required',
			'personal_info.email.value'			=>	'required|email'
		];

		$personal_info_validation = Validator::make($request->all(), $validation_rules1);

		if($personal_info_validation->fails()){
			throw new ClientException('Please fill in required fields', 'invalid_data_tab1', config('global.error_code'), 0);
		}

		return true;

	}

	/**
	 * validateContactInfo function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateContactInfo(Request $request) : bool {

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab2', 'tab_switch' => 1];

		if(!$request->has('contact_info')){
			throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
		}

		$contact_info = $request->input('contact_info');
		if(empty($contact_info)){
			throw new ClientException('Please have at least one contact info added', $response['validity'], config('global.error_code'), $response['tab_switch']);
		}

		$validation_rules2 = [
			'contact_info'							=>	'required|array|min:1',
			'contact_info.*.id'						=>	'required',
			'contact_info.*.first_name.value'		=>	'required',
			'contact_info.*.last_name.value'		=>	'required',
			'contact_info.*.email.value'			=>	'required|email',
		];

		$contact_info_validation = Validator::make($request->all(), $validation_rules2);
		if($contact_info_validation->fails()){
			throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
		}

		return true;

	}

	/**
	 * validateBillingInfo function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateBillingInfo(Request $request) : bool {

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab3', 'tab_switch' => 2];

		if(!$request->has('billing_info')){
			throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
		}

		$validation_rules3 = [
			'billing_info.street.value'						=>	'required',
			'billing_info.apt.value'						=>	'required',
			'billing_info.city.value'						=>	'required',
			'billing_info.state.value'						=>	'required',
			'billing_info.postal_code.value'				=>	'required',
			'billing_info.country.value'					=>	'required|exists:countries,id',
		];

		$billing_info_validation = Validator::make($request->all(), $validation_rules3);
		if($billing_info_validation->fails()){
			throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
		}

		return true;

	}


	/**
	 * validateShippingInfo function
	 *
	 * @param Request $request
	 * @param boolean $copy_to_shipping
	 * @return boolean
	 */
	public function validateShippingInfo(Request $request, bool $copy_to_shipping) : bool {

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab3', 'tab_switch' => 2];

		if(!$request->has('billing_info')){
			throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
		}

		if($copy_to_shipping === false){

			$validation_rules3 = [
				'shipping_info.street.value'						=>	'required',
				'shipping_info.apt.value'							=>	'required',
				'shipping_info.city.value'							=>	'required',
				'shipping_info.state.value'							=>	'required',
				'shipping_info.postal_code.value'					=>	'required',
				'shipping_info.country.value'						=>	'required|exists:countries,id',
			];

			$shipping_info_validation = Validator::make($request->all(), $validation_rules3);

			if($shipping_info_validation->fails()){
				throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
			}
			
			return true;

		}
		
		return true;

	}

	/**
	 * validateSettings function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	public function validateSettings(Request $request) : bool {

		$response = ['message' => 'Please fill in required fields', 'validity' => 'invalid_data_tab5', 'tab_switch' => 4];

		if(!$request->has('settings')){
			throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
		}

		$settings_rules = [
			'settings.currency.value'								=>	'required|exists:currencies,id',
			'settings.industry.value'								=>	'required|exists:industries,id',
			'settings.payment_terms.value'							=>	'required|in:0,7,14,30,60,90',
			'settings.quote_valid.value'							=>	'required|in:0,7,14,30,60,90',
			'settings.send_reminder.value'							=>	'required|in:0,1',
			'settings.size.value'									=>	'required',
		];

		$settings_validation = Validator::make($request->all(), $settings_rules);
		if($settings_validation->fails()){
			throw new ClientException($response['message'], $response['validity'], config('global.error_code'), $response['tab_switch']);
		}
		
		return true;

	}

	/**
	 * validateClientForUpsert function
	 *
	 * @param Request $request
	 * @param boolean $copy_to_shipping
	 * @return boolean
	 */
	public function validateClientForUpsert(Request $request, bool $copy_to_shipping) : bool {

		return 	$this->validatePersonInfo($request) &&
				$this->validateContactInfo($request) &&
				$this->validateBillingInfo($request) &&
				$this->validateShippingInfo($request, $copy_to_shipping) &&
				$this->validateSettings($request) &&
				$this->custom_fields->validateCustomFields($request, ClientsCustomField::class, 'invalid_data_tab4');

	}

}