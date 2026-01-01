<?php

namespace App\Services\PaymentSettingsStripe;

use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;

/**
 * PaymentSettingsStripeService class
 */
class PaymentSettingsStripeService{

	use SettingsDefault;

	private $type = PAYMENTS_STRIPE_TYPE;

	public function __construct(private SettingsSectionRepository $settings_section_repository){}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetch(int $company_id) : array {

		$stripe_settings = $this->settings_section_repository->fetchSettings($company_id, $this->type);

		if(!$stripe_settings){
			return $this->getDefaultStripeSettings();
		}

		$json = json_decode($stripe_settings->settings_json, true);
		
		try{
			$json['secret'] = decrypt($json['secret']);
		}catch(Exception $e){
			$json['secret'] = '';
		}

		return $json;

	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function update(array $data) : bool {

		$secret = encrypt($data['secret']);

		$stripe_settings = $this->settings_section_repository->fetchSettings($data['company_id'], $this->type);

		if(!$stripe_settings){
			$stripe_settings = $this->settings_section_repository->createObj($data['company_id'], $this->type);
		}

		$json = json_encode([
			'secret'	=>	$secret,
		]);

		return $this->settings_section_repository->updateByObj($json, $stripe_settings);

	}
	
	/**
	 * destroy function
	 *
	 * @param integer $company_id
	 * @return void
	 */
	public function destroy(int $company_id) : void {
		$this->settings_section_repository->destroy($company_id, $this->type);
	}

}