<?php

namespace App\Services\PaymentSettingsPaypal;

use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;

/**
 * PaymentSettingsPaypalService class
 */
class PaymentSettingsPaypalService{

	use SettingsDefault;

	private $type = PAYMENTS_PAYPAL_TYPE;

	/**
	 * __construct function
	 *
	 * @param SettingsSectionRepository $settings_section_repository
	 */
	public function __construct(private SettingsSectionRepository $settings_section_repository){}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetch(int $company_id) : array {

		$paypal_settings = $this->settings_section_repository->fetchSettings($company_id, $this->type);

		if(!$paypal_settings){
			return $this->getDefaultPayPalSettings();
		}

		$json = json_decode($paypal_settings->settings_json, true);

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

		$paypal_settings = $this->settings_section_repository->fetchSettings($data['company_id'], $this->type);

		if(!$paypal_settings){
			$paypal_settings = $this->settings_section_repository->createObj($data['company_id'], $this->type);
		}

		$json = json_encode([
			'client_id'	=>	$data['client_id'],
			'app_id'	=>	$data['app_id'],
			'secret'	=>	$secret,
			'mode'		=>	$data['mode'],
		]);

		return $this->settings_section_repository->updateByObj($json, $paypal_settings);

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