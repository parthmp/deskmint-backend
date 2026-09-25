<?php

namespace App\Services\EmailSettingsContent;

use App\Enums\EmailSettings\EmailSettingsContent;
use App\Exceptions\EmailSettingsContentException;
use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;

/**
 * EmailSettingsContentService class
 */
class EmailSettingsContentService{

	use SettingsDefault;

	private string $type = ESC_EMAIL_CONTENT_TYPE;

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
	 * fetchRecordBytype function
	 *
	 * @param integer $company_id
	 * @param string $key
	 * @return SettingsSection|null
	 */
	public function fetchRecordBytype(int $company_id, string $key) : ?SettingsSection {
		return $this->settings_section_repository->fetchSettings($company_id, $key);
	}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @param string $key
	 * @return array|null
	 */
	public function fetch(int $company_id, string $key) : ?array {

		$record = $this->fetchRecordBytype($company_id, $key);

		if($record){
			return json_decode($record->settings_json, true);
		}

		if((string) $key === (string) EmailSettingsContent::INVOICES->value && !$record){
			return $this->getDefaultInvoicesEmailContentSettings();
		}

		if((string) $key === (string) EmailSettingsContent::PAYMENT_REQUESTS->value && !$record){
			return $this->getDefaultPaymentRequestsEmailContentSettings();
		}

		if((string) $key === (string) EmailSettingsContent::RECURRING_INVOICES->value && !$record){
			return $this->getDefaultRecurringInvoiceEmailContentSettings();
		}
		

		return null;

	}

	/**
	 * updateByObj function
	 *
	 * @param array $data
	 * @param SettingsSection|null $email_content
	 * @return boolean
	 */
	public function updateByObj(array $data, SettingsSection|null $email_content) : bool {

		try{

			if(!$email_content){
				$email_content = $this->settings_section_repository->createObj($data['company_id'], $this->type);
			}
			
			$json_string = json_encode([
				'email_content_invoice'						=>	$data['email_content_invoice'],
				'email_content_reminder'					=>	$data['email_content_reminder'],
				'email_content_payment_request'				=>	$data['email_content_payment_request'],
				'email_content_reminder_payment_request'	=>	$data['email_content_reminder_payment_request'],
			]);

			return $this->settings_section_repository->updateByObj($json_string, $email_content);

		}catch(Exception $e){
			throw new Exception('unable to update email content settings');
		}

	}

	/**
	 * validateContent function
	 *
	 * @param array $data
	 * @param array $keys
	 * @return boolean
	 */
	private function validateContent(array $data, array $keys) : bool {

		if(count($data) !== count($keys)){
			return false;
		}

		foreach($keys as $key){

			if(!isset($data[$key])){
				return false;
			}
		}

		return true;
	}

	/**
	 * filterAndValidateContentData function
	 *
	 * @param array $data
	 * @param string $key
	 * @return array
	 */
	public function filterAndValidateContentData(array $data, string $key) : array {

		$keys = [];

		if((string) $key === (string) EmailSettingsContent::INVOICES->value){
			$keys = ['email_content_invoice', 'email_content_reminder'];
		}

		if((string) $key === (string) EmailSettingsContent::PAYMENT_REQUESTS->value){
			$keys = ['email_content_payment_request', 'email_content_reminder_payment_request'];
		}

		if((string) $key === (string) EmailSettingsContent::RECURRING_INVOICES->value){
			$keys = ['recurring_invoice_email_content'];
		}

		if(!$this->validateContent($data, $keys)){
			throw new EmailSettingsContentException('Invalid data provided', 'invalid_data', (int) config('global.error_code'));
		}

		$return = [];

		foreach($data as $entry_key => $entry){
			$entry_key_l = (string) Sanitize::input($entry_key);
			$entry_l = (string) Sanitize::input($entry);
			$return[$entry_key_l] = $entry_l;
		}

		return $return;

	}

	/**
	 * save function
	 *
	 * @param integer $company_id
	 * @param array $data
	 * @return void
	 */
	public function save(int $company_id, array $data, string $key) : void {

		$json = json_encode($data);
		$this->settings_section_repository->upsert($company_id, $json, $key);

	}

}