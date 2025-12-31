<?php

namespace App\Services\InvoiceSettingsAPF;

use App\Models\SettingsSection;
use App\Repositories\InvoiceSettingsAPF\InvoiceSettingsAPFRepository;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Illuminate\Database\Eloquent\Collection;

class InvoiceSettingsAPFService{

	private $type = ISC_PRODUCT_COLUMNS_TYPE;

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

	private function modifyJson(SettingsSection|array|null $saved_setting, Collection $fields){

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
						'mapped'	=>	null,
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

	public function regenerateSettings(int $company_id) : bool {

		$fields = $this->fetchCompanyById($company_id);

		$saved_setting = $this->settings_section_repository->fetchSettings($company_id, $this->type);

		$changes_made = false;
		$old_json = [];
		
		if($saved_setting){
			$arr = $this->modifyJson($saved_setting, $fields);
			$changes_made = $arr['changes_made'];
			$old_json = $arr['old_json'];
		}

		if($changes_made){
			$this->settings_section_repository->updateByObj($old_json, $saved_setting);
		}

		return $changes_made;
	}


}