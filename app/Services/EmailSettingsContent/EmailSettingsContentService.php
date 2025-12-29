<?php

namespace App\Services\EmailSettingsContent;

use App\Models\SettingsSection;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;

/**
 * EmailSettingsContentService class
 */
class EmailSettingsContentService{

	use SettingsDefault;

	/**
	 * __construct function
	 *
	 * @param SettingsSectionRepository $settings_section_repository
	 */
	public function __construct(private SettingsSectionRepository $settings_section_repository){
	}
	
	/**
	 * fetchRecord function
	 *
	 * @param integer $company_id
	 * @return SettingsSection|null
	 */
	public function fetchRecord(int $company_id) : SettingsSection|null {
		return $this->settings_section_repository->fetchSettings($company_id, ESC_EMAIL_CONTENT_TYPE);
	}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetch(int $company_id) : array {

		$email_content = $this->fetchRecord($company_id);

		if(!$email_content){
			return $this->getDefaultEmailContentSettings();
		}

		return json_decode($email_content->settings_json, true);

	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function updateByObj(array $data, $email_content) : bool {

		try{

			if(!$email_content){
				$email_content = $this->settings_section_repository->createObj($data['company_id'], ESC_EMAIL_CONTENT_TYPE);
			}
			
			$json_string = json_encode([
				'email_content_invoice'		=>	$data['email_content_invoice'],
				'email_content_reminder'	=>	$data['email_content_reminder'],
				'payment_details'			=>	$data['payment_details']
			]);

			$email_content->settings_json = $json_string;

			return $email_content->save();

		}catch(Exception $e){
			throw new Exception('unable to update email content settings');
		}

	}

}