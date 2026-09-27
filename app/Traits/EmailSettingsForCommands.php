<?php

namespace App\Traits;

use App\Enums\EmailSettings\EmailSettingsContent;
use App\Models\SettingsSection;

trait EmailSettingsForCommands {

	use SettingsDefault;

	/**
	 * getSettings function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @param array $default
	 * @return array
	 */
	private function getSettings(int $company_id, string $type, array $default) : array {

		$reminds_default = $this->getDefaultEmailRemindersSettings();
		$content_default = $default;

		$fetched_settings = SettingsSection::whereIn('type', [ESC_EMAIL_REMINDERS_TYPE, $type])->where('company_id', '=', $company_id)->get()->toArray();

		$settings['reminders'] = [
			'days_gap'		=>	(int) $reminds_default['days_gap'],
			'send_n_times'	=>	(int) $reminds_default['send_n_times'],
		];

		return [
			'default'	=>	$content_default,
			'fetched'	=>	$fetched_settings,
			'settings'	=>	$settings
		];

	}

	/**
	 * fetchEmailSettings function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @return array
	 */
	private function fetchEmailSettings(int $company_id, string $type) : array {

		$data = $this->getSettings($company_id, EmailSettingsContent::INVOICES->value, $this->getDefaultInvoicesEmailContentSettings());
		$settings = $data['settings'];
		$settings['content'] = $data['default'][$type];

		foreach($data['fetched'] as $temp){

			if(isset($temp['settings_json'])){

				$json = json_decode($temp['settings_json'], true);

				if($temp['type'] === ESC_EMAIL_REMINDERS_TYPE){
					$settings['reminders']['days_gap'] = (int) $json['days_gap'];
					$settings['reminders']['send_n_times'] =  (int) $json['send_n_times'];
				}else if($temp['type'] === EmailSettingsContent::INVOICES->value){
					$settings['content'] = $json[$type];
				}

			}

		}

		return $settings;

	}

	/**
	 * fetchEmailSettingsForPaymentRequests function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @return array
	 */
	private function fetchEmailSettingsForPaymentRequests(int $company_id, string $type) : array {

		$data = $this->getSettings($company_id, EmailSettingsContent::PAYMENT_REQUESTS->value, $this->getDefaultPaymentRequestsEmailContentSettings());

		$settings = $data['settings'];
		$settings['content'] = $data['default'][$type];

		foreach($data['fetched'] as $temp){

			if(isset($temp['settings_json'])){

				$json = json_decode($temp['settings_json'], true);

				if($temp['type'] === ESC_EMAIL_REMINDERS_TYPE){
					$settings['reminders']['days_gap'] = (int) $json['days_gap'];
					$settings['reminders']['send_n_times'] =  (int) $json['send_n_times'];
				}else if($temp['type'] === EmailSettingsContent::PAYMENT_REQUESTS->value){
					$settings['content'] = $json[$type];
				}

			}

		}

		return $settings;

	}


}