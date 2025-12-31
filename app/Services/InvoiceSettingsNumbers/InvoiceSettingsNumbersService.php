<?php

namespace App\Services\InvoiceSettingsNumbers;

use App\Models\SettingsSection;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;

/**
 * InvoiceSettingsNumbersService class
 */
class InvoiceSettingsNumbersService{

	use SettingsDefault;

	private $type = ISC_INVOICE_NUMBERS_TYPE;

	public function __construct(private SettingsSectionRepository $settings_section_repository){}

	/**
	 * fetchRecord function
	 *
	 * @param integer $company_id
	 * @return SettingsSection|null
	 */
	public function fetchRecord(int $company_id) : SettingsSection|null {
		return $this->settings_section_repository->fetchSettings($company_id, $this->type);
	}

	public function fetch(int $company_id){
		try{

			$settings = $this->fetchRecord($company_id);

			if($settings){
				return json_decode($settings->settings_json);
			}

			return $this->getDefaultInvoiceNumbersSettings();
					
		}catch(Exception $e){
			throw new Exception('unable to fetch invoice numbers settings');
		}
	}

	/**
	 * ifValidNumberPaddingOption function
	 *
	 * @param string $number_padding
	 * @return boolean
	 */
	public function ifValidNumberPaddingOption(string $number_padding) : bool {
		$number_padding_options = ['1', '0001', '00001', '000001', '0000001', '00000001'];
		return in_array($number_padding, $number_padding_options);
	}

	/**
	 * ifValidNumberResetCounterOption function
	 *
	 * @param string $reset_counter
	 * @return boolean
	 */
	public function ifValidNumberResetCounterOption(string $reset_counter) : bool {
		$reset_counter_options = ['never', 'daily', 'weekly', 'two_weeks', 'monthly', 'two_months', 'three_months', 'four_months', 'six_months', 'yearly'];
		return in_array($reset_counter, $reset_counter_options);
	}

	/**
	 * updateByObj function
	 *
	 * @param array $data
	 * @param SettingsSection|null $record
	 * @return boolean
	 */
	public function updateByObj(array $data, SettingsSection|null $record) : bool {

		try{

			if(!$record){
				$record = $this->settings_section_repository->createObj($data['company_id'], $this->type);
			}
			
			$json_string = json_encode([
				'number_padding' 	=> 	$data['number_padding'],
				'reset_counter' 	=> 	$data['reset_counter'],
				'number_pattern'	=>	$data['number_pattern']
			]);

			return $this->settings_section_repository->updateByObj($json_string, $record);

		}catch(Exception $e){
			throw new Exception('unable to update invoice numbers settings');
		}

	}

	/**
	 * resetInvoiceNumbers function
	 *
	 * @param integer $company_id
	 * @return boolean
	 */
	public function resetInvoiceNumbers(int $company_id) : bool {

		$reset_type = ISC_INVOICE_NUMBER_RESET_TYPE;

		$json = json_encode([
			'reset'	=>	"1"
		]);

		$record = $this->settings_section_repository->fetchSettings($company_id, $reset_type);

		if(!$record){
			$record = $this->settings_section_repository->createObj($company_id, $reset_type);
			$record->company_id = $company_id;
			$record->type = ISC_INVOICE_NUMBER_RESET_TYPE;
		}
		
		$record->settings_json = $json;

		return $record->save();

	}

}