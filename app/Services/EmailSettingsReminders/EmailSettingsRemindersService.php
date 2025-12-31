<?php

namespace App\Services\EmailSettingsReminders;

use App\Models\SettingsSection;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;

/**
 * EmailSettingsRemindersService class
 */
class EmailSettingsRemindersService{

	use SettingsDefault;

	private $type = ESC_EMAIL_REMINDERS_TYPE;

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
		return $this->settings_section_repository->fetchSettings($company_id, $this->type);
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
			return $this->getDefaultEmailRemindersSettings();
		}

		return json_decode($email_content->settings_json, true);

	}

	/**
	 * updateByObj function
	 *
	 * @param array $data
	 * @param SettingsSection|null $setting_record
	 * @return boolean
	 */
	public function updateByObj(array $data, SettingsSection|null $setting_record) : bool {

		try{

			if(!$setting_record){
				$setting_record = $this->settings_section_repository->createObj($data['company_id'], $this->type);
			}

			
			$json_string = json_encode([
				'send_n_times'		=>	$data['send_n_times'],
				'days_gap'			=>	$data['days_gap']
			]);

			return $this->settings_section_repository->updateByObj($json_string, $setting_record);

		}catch(Exception $e){
			throw new Exception('unable to update email reminders settings');
		}

	}

}