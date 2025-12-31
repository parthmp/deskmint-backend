<?php

namespace App\Services\EmailSettingsSMTP;

use App\Models\SettingsSection;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Support\Facades\Mail;

/**
 * EmailSettingsSMTPService class
 */
class EmailSettingsSMTPService{

	use SettingsDefault;

	private $type = ESC_EMAIL_SMTP_TYPE;
	
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

		$record = $this->fetchRecord($company_id);

		if(!$record){
			return $this->getDefaultEmailSMTPSettings();
		}

		$record = json_decode($record->settings_json, true);

		try{
			$record['password'] = decrypt($record['password']);
		}catch(Exception $e){
			$record['password'] = '';
		}

		return $record;

	}

	/**
	 * sendTestEmail function
	 *
	 * @param array $data
	 * @return void
	 */
	public function sendTestEmail(array $data) : void {

		try{
			config([
				'mail.mailers.smtp.host' => $data['host'],
				'mail.mailers.smtp.port' => $data['port'],
				'mail.mailers.smtp.username' => $data['username'],
				'mail.mailers.smtp.password' => $data['password'],
				'mail.mailers.smtp.encryption' => $data['encryption'],
				'mail.from.address' => $data['mail_from_address'],
				'mail.from.name' => $data['mail_from_name'],
				'mail.reply_to.address' => $data['reply_to_address'],
				'mail.reply_to.name' => $data['mail_from_name']
			]);

			Mail::raw('This is a test email from your SMTP configuration.', function ($message) use ($data) {
				$message->to($data['test_email_address'])->subject('DeskMint - SMTP Test Email');
			});
		}catch(Exception $e){
			throw new Exception('could not send test email');
		}
		
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
				'host'					=>	$data['host'],
				'port'					=>	$data['port'],
				'username'				=>	$data['username'],
				'password'				=>	encrypt($data['password']),
				'encryption'			=>	$data['encryption'],
				'mail_from_address'		=>	$data['mail_from_address'],
				'mail_from_name'		=>	$data['mail_from_name'],
				'reply_to_address'		=>	$data['reply_to_address'],
				'test_email_address'	=>	$data['test_email_address']
			]);

			return $this->settings_section_repository->updateByObj($json_string, $setting_record);

		}catch(Exception $e){
			throw new Exception('unable to update email smtp settings');
		}

	}

}