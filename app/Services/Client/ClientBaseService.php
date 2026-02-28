<?php

namespace App\Services\Client;

use App\Helpers\Sanitize;
use App\Models\ClientCustomFieldValue;
use App\Models\ClientsCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Repositories\Client\ClientContactInfoRepository;
use App\Repositories\Client\ClientRepository;
use App\Services\Client\Exceptions\ClientException;
use Exception;
use Illuminate\Http\Request;

class ClientBaseService{

	public function __construct(
		private ClientRepository $client_repository,
		private ClientValidationService $client_validation_service,
		private ClientContactInfoRepository $client_contact_info_repository,
		private CustomFields $custom_fields
	){}

	/**
	 * upsertContactInfoForClient function
	 *
	 * @param Request $request
	 * @param integer $client_id
	 * @param boolean $add
	 * @return void
	 */
	private function upsertContactInfoForClient(Request $request, int $client_id, bool $add = true) : void {

		$contact_info = $request->input('contact_info');

		$upsert = [];

		foreach($contact_info as $info){

			$info_id = Sanitize::input($info['id']);
			$first_name = Sanitize::input($info['first_name']['value']);
			$last_name = Sanitize::input($info['last_name']['value']);
			$email = Sanitize::input($info['email']['value']);
			$phone = Sanitize::input($info['phone']['value'].'');

			$temp_upsert = [];

			if(!$add){
				$temp_upsert['id'] = $info_id;
			}
			$temp_upsert['client_id'] = $client_id;
			$temp_upsert['first_name'] = $first_name;
			$temp_upsert['last_name'] = $last_name;
			$temp_upsert['email'] = $email;
			$temp_upsert['phone'] = $phone;
			$temp_upsert['deleted_at'] = null;

			$upsert[] = $temp_upsert;

		}

		if(!empty($upsert)){
			$this->client_contact_info_repository->upsertInfo($upsert, ['id'], ['first_name', 'last_name', 'email', 'phone', 'deleted_at']);
		}

	}
	
	/**
	 * saveOrUpdateClient function
	 *
	 * @param Request $request
	 * @param boolean $add
	 * @return boolean
	 */
	public function saveOrUpdateClient(Request $request, $add = true) : bool {
		
		if(!$add){

			$client_id = Sanitize::input($request->segment(3));
			$client = $this->client_repository->fetchById($client_id);
			
			if(!$client){
				throw new ClientException('Invalid request', 'invalid_request', config('global.error_code'));
			}

		}

		$copy_to_shipping = false;
		if($request->has('copy_to_shipping')){
			$copy_to_shipping = Sanitize::input($request->input('copy_to_shipping'));
			$copy_to_shipping = filter_var($copy_to_shipping.'', FILTER_VALIDATE_BOOLEAN);
		}

		try{

			/**
			 * this should throw ClientException if invalid.
			 */
			$this->client_validation_service->validateClientForUpsert($request, $copy_to_shipping);

			try{

				$company_id = Sanitize::input($request->input('company_id'));
				$personal_info_first_name = Sanitize::input($request->input('personal_info.first_name.value'));
				$personal_info_last_name = Sanitize::input($request->input('personal_info.last_name.value'));
				$personal_info_tax_id = Sanitize::input($request->input('personal_info.tax_id.value').'');
				$website = Sanitize::input($request->input('personal_info.website.value').'');
				$phone = Sanitize::input($request->input('personal_info.phone.value').'');
				$email = Sanitize::input($request->input('personal_info.email.value'));
				$billing_street = Sanitize::input($request->input('billing_info.street.value'));
				$billing_apt = Sanitize::input($request->input('billing_info.apt.value'));
				$billing_city = Sanitize::input($request->input('billing_info.city.value'));
				$billing_state = Sanitize::input($request->input('billing_info.state.value'));
				$billing_postal_code = Sanitize::input($request->input('billing_info.postal_code.value'));
				$billing_country_id = Sanitize::input($request->input('billing_info.country.value'));

				/* init shipping info */
				$shipping_street = Sanitize::input($request->input('shipping_info.street.value').'');
				$shipping_apt = Sanitize::input($request->input('shipping_info.apt.value').'');
				$shipping_city = Sanitize::input($request->input('shipping_info.city.value').'');
				$shipping_state = Sanitize::input($request->input('shipping_info.state.value').'');
				$shipping_postal_code = Sanitize::input($request->input('shipping_info.postal_code.value').'');
				$shipping_country_id = Sanitize::input($request->input('shipping_info.country.value').'');
				if($copy_to_shipping){
					$shipping_street = $billing_street;
					$shipping_apt = $billing_apt;
					$shipping_city = $billing_city;
					$shipping_state = $billing_state;
					$shipping_postal_code = $billing_postal_code;
					$shipping_country_id = $billing_country_id;
				}

				/* client settings */
				$currency_id = Sanitize::input($request->input('settings.currency.value'));
				$payment_terms = Sanitize::input($request->input('settings.payment_terms.value'));
				$quote_valid_days = Sanitize::input($request->input('settings.quote_valid.value'));
				$send_reminders = Sanitize::input($request->input('settings.send_reminder.value'));
				$size = Sanitize::input($request->input('settings.size.value'));
				$industry_id = Sanitize::input($request->input('settings.industry.value'));

				if($add){
					$client = $this->client_repository->createEmpty();
				}

				$data = [
					'company_id'				=>	$company_id,
					'personal_info_first_name'	=>	$personal_info_first_name,
					'personal_info_last_name'	=>	$personal_info_last_name,
					'personal_info_tax_id'		=>	$personal_info_tax_id,
					'website'					=>	$website,
					'email'						=>	$email,
					'phone'						=>	$phone,
					'billing_street'			=>	$billing_street,
					'billing_apt'				=>	$billing_apt,
					'billing_city'				=>	$billing_city,
					'billing_state'				=>	$billing_state,
					'billing_postal_code'		=>	$billing_postal_code,
					'billing_country_id'		=>	$billing_country_id,
					'shipping_street'			=>	$shipping_street,
					'shipping_apt'				=>	$shipping_apt,
					'shipping_city'				=>	$shipping_city,
					'shipping_state'			=>	$shipping_state,
					'shipping_postal_code'		=>	$shipping_postal_code,
					'shipping_country_id'		=>	$shipping_country_id,
					'currency_id'				=>	$currency_id,
					'payment_terms'				=>	$payment_terms,
					'quote_valid_days'			=>	$quote_valid_days,
					'send_reminders'			=>	$send_reminders,
					'size'						=>	$size,
					'industry_id'				=>	$industry_id
				];

				[$saved, $client_id] = $this->client_repository->createOrUpdate($client, $data);

				$this->upsertContactInfoForClient($request, $client_id, $add);
				$this->custom_fields->upsertCustomFieldValues($request, $client_id, ClientsCustomField::class, ClientCustomFieldValue::class, 'clients_flat', 'client', $add);

				if($saved){
					return true;
				}else{
					throw new Exception();
				}
				
				

			}catch(Exception $e){
				throw new Exception();
			}

		}catch(ClientException $e){
			throw new ClientException($e->getMessage(), $e->getValidity(), $e->getCode(), $e->getTab());
		}

	}
	

}