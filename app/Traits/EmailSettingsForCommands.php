<?php

namespace App\Traits;

use App\Models\SettingsSection;

trait EmailSettingsForCommands {

	use SettingsDefault;

	/**
	 * fetchEmailSettings function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @return array
	 */
	private function fetchEmailSettings(int $company_id, string $type) : array {

		$reminds_default = $this->getDefaultEmailRemindersSettings();
		$content_default = $this->getDefaultEmailContentSettings();

		$fetched_settings = SettingsSection::whereIn('type', [ESC_EMAIL_REMINDERS_TYPE, ESC_EMAIL_CONTENT_TYPE])->where('company_id', '=', $company_id)->get()->toArray();

		$settings['reminders'] = [
			'days_gap'		=>	(int) $reminds_default['days_gap'],
			'send_n_times'	=>	(int) $reminds_default['send_n_times'],
		];

		$settings['content'] = $content_default[$type];

		foreach($fetched_settings as $temp){

			if(isset($temp['settings_json'])){

				$json = json_decode($temp['settings_json'], true);

				if($temp['type'] === ESC_EMAIL_REMINDERS_TYPE){
					$settings['reminders']['days_gap'] = (int) $json['days_gap'];
					$settings['reminders']['send_n_times'] =  (int) $json['send_n_times'];
				}else if($temp['type'] === ESC_EMAIL_CONTENT_TYPE){
					$settings['content'] = $json[$type];
				}

			}

		}

		return $settings;

	}


}