<?php

namespace App\Services\InvoiceSettingsGeneral;

use App\Models\SettingsSection;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Support\Facades\Storage;

/**
 * InvoiceSettingsGeneralService class
 */
class InvoiceSettingsGeneralService{

	use SettingsDefault;

	private $type = ISC_INVOICE_GENERAL_DETAILS_TYPE;

	public function __construct(private SettingsSectionRepository $settings_section_repository){}
	
	/**
	 * fetchTemplates function
	 *
	 * @return array
	 */
	public function fetchTemplates() : array {

		$files = Storage::disk('invoice_templates')->files();

		return array_map(function($file){
			return pathinfo(strtolower($file), PATHINFO_FILENAME);
		}, $files);

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

		$settings = $this->fetchRecord($company_id);

		$default = $this->getDefaultInvoiceGeneralSettings();

		return [
			'settings' 	=> $settings,
			'default' 	=> $default
		];


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
				'template'				=>	$data['template'],
				'font_size'				=>	$data['font_size'],
				'logo_size'				=>	$data['logo_size'],
				'primary_color'			=>	$data['primary_color'],
				'secondary_color'		=>	$data['secondary_color'],
				'e_invoice_on'			=>	$data['e_invoice']
			]);

			return $this->settings_section_repository->updateByObj($json_string, $setting_record);

		}catch(Exception $e){
			throw new Exception('unable to update invoice general settings');
		}

	}

}