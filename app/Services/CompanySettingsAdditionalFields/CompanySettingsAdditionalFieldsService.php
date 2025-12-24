<?php

namespace App\Services\CompanySettingsAdditionalFields;

use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use App\Repositories\CompanySettingsAdditionalFields\CompanySettingsAdditionalFieldsRepository;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Exception;

class CompanySettingsAdditionalFieldsService{

	/**
	 * __construct function
	 *
	 * @param CompanySettingsAdditionalFieldsRepository $company_settings_additional_fields_repository
	 * @param SettingsSectionRepository $settings_section_repository
	 */
	public function __construct(private CompanySettingsAdditionalFieldsRepository $company_settings_additional_fields_repository, private SettingsSectionRepository $settings_section_repository){
	}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetch(int $company_id) : array {
		$company = $this->company_settings_additional_fields_repository->fetchDefaulyByComapnyId($company_id);
		$fields = $company->additional_fields->toArray() ? $company->additional_fields->toArray() : [];
		return array_reverse($fields);
	}

	/**
	 * prepareData function
	 *
	 * @param array $data
	 * @return array
	 */
	private function prepareData(array $data) : array {
		
		$all_fields = $data['all_fields'];
		$company_id = (int) Sanitize::input($data['company_id']);
		
		$upsert = [];

		foreach($all_fields  as $field){
			
			$element = [];

			if(isset($field['id']) && $field['id']){

				$field_id = Sanitize::input($field['id']);

				if($field_id !== ''){
					$element['id'] = $field_id;
				}

			}else{
				$element['id'] = null;
			}

			$element['company_id'] = $company_id;
			$element['label'] = Sanitize::input($field['label']);
			$element['value'] = Sanitize::input(($field['value']) ? $field['value'] : '');

			if(!empty($element)){
				array_push($upsert, $element);
			}

		}

		return $upsert;
		
	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @return void
	 */
	public function update(array $data){
		$data = $this->prepareData($data);
		$this->company_settings_additional_fields_repository->upsert($data);
	}

	/**
	 * checkIfExists function
	 *
	 * @param integer $id
	 * @param integer $company_id
	 * @return AdditionalCompanyField|null
	 */
	private function checkIfExists(int $id, int $company_id) : AdditionalCompanyField|null {
		
		$additonal_field = $this->company_settings_additional_fields_repository->fetchByIdWithCompanyId($id, $company_id);
		if(!$additonal_field){
			return null;
		}

		return $additonal_field;
	}

	/**
	 * modifySettingsJson function
	 *
	 * @param integer $id
	 * @param integer $company_id
	 * @return void
	 */
	private function modifySettingsJson(int $id, int $company_id){

		$settings = $this->settings_section_repository->fetchCompanyDetailsAndAddress($company_id);
		
		if($settings){

			for($x = 0 ; $x < count($settings) ; $x++){

				$modified = [];

				$json = json_decode($settings[$x]->settings_json, true);
				
				for($z = 0 ; $z < count($json) ; $z++){
					if($json[$z]['type'] === 'custom'){
						
						if($json[$z]['id_column'] !== (int) $id){
							$modified[] = $json[$z];
						}
					}else{
						$modified[] = $json[$z];
					}
				}
				
				$settings[$x]->settings_json = json_encode($modified);
				$settings[$x]->save();

			}

		}

	}
	
	/**
	 * destroy function
	 *
	 * @param array $data
	 * @return void
	 */
	public function destroy(array $data){
		
		$company_id = (int) Sanitize::input($data['company_id']);
		$id = (int) Sanitize::input($data['id']);

		$additonal_field = $this->checkIfExists($id, $company_id);

		if(!$additonal_field){
			throw new Exception('could not find the record');
		}

		$this->modifySettingsJson($id, $company_id);

		if(!$additonal_field->delete()){
			throw new Exception('could not delete the record');
		}

	}

}