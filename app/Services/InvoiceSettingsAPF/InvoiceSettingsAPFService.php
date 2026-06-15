<?php

namespace App\Services\InvoiceSettingsAPF;

use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Repositories\InvoiceSettingsAPF\InvoiceSettingsAPFRepository;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Illuminate\Database\Eloquent\Collection;

class InvoiceSettingsAPFService{

	private string $type = ISC_PRODUCT_COLUMNS_TYPE;

	public function __construct(private InvoiceSettingsAPFRepository $invoice_settings_apf_repository, private SettingsSectionRepository $settings_section_repository){}

	/**
	 * fetchCompanyById function
	 *
	 * @param integer $company_id
	 * @return Collection|null
	 */
	private function fetchCompanyById(int $company_id) : Collection|null {
		return $this->invoice_settings_apf_repository->fetchByCompanyId($company_id);
	}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetch(int $company_id) : array {

		$fields = $this->fetchCompanyById($company_id);

		$labels = [];
		$types = [];
		$taxes = [];

		foreach($fields as $field){

			$labels[] = [
				'id'			=>		$field->id,
				'value'			=>		$field->label,
				'error'			=>		'',
				'show_errors'	=>		false
			];

			$types[] = [
				'id'			=>		$field->id,
				'value'			=>		$field->type,
				'error'			=>		''
			];

			$taxes[] = [
				'id'			=>		$field->id,
				'value'			=>		$field->tax_rate
			];

		}

		return [
			'labels'	=>		$labels,
			'types'		=>		$types,
			'taxes'		=>		$taxes
		];

	}

	/**
	 * modifyJson function
	 *
	 * @param SettingsSection|array|null $saved_setting
	 * @param Collection $fields
	 * @return array
	 */
	private function modifyJson(SettingsSection|array|null $saved_setting, Collection $fields) : array {

		$old_json = [];

		$changes_made = false;

		$old_json = json_decode($saved_setting->settings_json, true);
		
		for($z = 0 ; $z < count($fields) ; $z++){

			for($x = 0 ; $x < count($old_json) ; $x++){
				if(($old_json[$x]['type'] === 'custom' && (int) $old_json[$x]['id_column'] === (int) $fields[$z]['id']) && ($old_json[$x]['text'] !== $fields[$z]['label'] || $old_json[$x]['type'] !== $fields[$z]['type'] || (int) $old_json[$x]['tax_rate'] !== (int) $fields[$z]['tax_rate'])){
					
					$old_json[$x] = [
						'id'		=>	$old_json[$x]['id'],
						'tax'		=>	$fields[$z]['type'] === 'tax' ? true : false,
						'text'		=>	$fields[$z]['label'],
						'type'		=>	'custom',
						'value'		=>	$fields[$z]['label'],
						'mapped'	=>	'',
						'tax_rate'	=>	$fields[$z]['tax_rate'],
						'id_column'	=>	$fields[$z]['id']
					];

					$changes_made = true;
				}

			}

		}

		return [
			'changes_made'	=>	$changes_made,
			'old_json'		=>	$old_json
		];

	}

	/**
	 * regenerateSettings function
	 *
	 * @param integer $company_id
	 * @return boolean
	 */
	public function regenerateSettings(int $company_id) : bool {

		$fields = $this->fetchCompanyById($company_id);

		$saved_setting = $this->settings_section_repository->fetchSettings($company_id, $this->type);
		
		$changes_made = false;
		$old_json = [];
		
		if($saved_setting){

			$arr = $this->modifyJson($saved_setting, $fields);
			$changes_made = $arr['changes_made'];
			$old_json = $arr['old_json'];

			if($changes_made){
				$this->settings_section_repository->updateByObj(json_encode($old_json), $saved_setting);
			}

		}

		return $changes_made;

	}

	/**
	 * ifInvalidIdsPresent function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function ifInvalidIdsPresent(array $data) : bool {
		
		$invalid_ids = false;

		for($z = 0 ; $z < count($data['labels']) ; $z++){

			if($data['labels'][$z]['id'] !== $data['types'][$z]['id'] || $data['labels'][$z]['id'] !== $data['taxes'][$z]['id'] || $data['types'][$z]['id'] !== $data['taxes'][$z]['id']){
				$invalid_ids = true;
				break;
			}

		}

		return $invalid_ids;
	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @return void
	 */
	public function update(array $data) : void {

		$labels = $data['labels'];
		$company_id = $data['company_id'];
		$types = $data['types'];
		$taxes = $data['taxes'];
	
		$upsert = [];
		/* now validate for ids */
		for($z = 0 ; $z < count($labels) ; $z++){
			
			$upsert[] = [
				'id'				=>	$labels[$z]['id'],
				'company_id'		=>	$company_id,
				'label'				=>	Sanitize::input($labels[$z]['value'].''),
				'type'				=>	Sanitize::input($types[$z]['value'].''),
				'tax_rate'			=>	(float) Sanitize::input($taxes[$z]['value']),
			];

		}
		

		$this->invoice_settings_apf_repository->upsert($upsert);

	}

	/**
	 * destroy function
	 *
	 * @param integer $id
	 * @return void
	 */
	public function destroy(int $id) : void {
		$this->invoice_settings_apf_repository->destroy($id);
	}

	/**
	 * removeDeletedFromSettingsSection function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @return boolean
	 */
	public function removeDeletedFromSettingsSection(int $company_id, int $id) : bool {

		$settings = $this->settings_section_repository->fetchSettings($company_id, $this->type);
			
		if($settings){

			$modified = [];

			$json = json_decode($settings->settings_json, true);
			
			for($z = 0 ; $z < count($json) ; $z++){
				if($json[$z]['type'] === 'custom'){
					
					if((string) $json[$z]['id_column'] !== (string) $id){
						$modified[] = $json[$z];
					}
				}else{
					$modified[] = $json[$z];
				}
			}

			return $this->settings_section_repository->updateByObj(json_encode($modified), $settings);

		}

		return false;

	}

}