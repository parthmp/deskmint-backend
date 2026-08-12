<?php

namespace App\Modules\ArrangedFields;

use App\Helpers\General;
use App\Models\SettingsSection;
use App\Modules\ArrangedFields\Contracts\ArrangedFieldsInterface;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Support\Facades\Schema;

class ArrangedFields{

	use SettingsDefault;

	private ArrangedFieldsInterface $arranged_object;
	private SettingsSectionRepository $settings_section_repository;
	private array $data;
	
	public function __construct(ArrangedFieldsInterface $obj, array $data, ?SettingsSectionRepository $settings_section_repository = null){
		$this->arranged_object = $obj;
		$this->data = $data;
		$this->settings_section_repository = $settings_section_repository ?? new SettingsSectionRepository();
	}

	public function fetchArrangedFieldsData(string $model = ''){
		
		try{
			
			$default_data = $this->arranged_object->fetchDefaultArrangedFieldsData($this->data['company_id']);
			
			$settings = $this->settings_section_repository->fetchSettings((int) $this->data['company_id'], $this->arranged_object->getType());
			
			if($settings){
				
				$dropdown_fields = [];

				$saved_rows = json_decode($settings->settings_json, true);
				
				/* filter rows here start */

				if($model !== ''){

					$temp_saved_rows = [];

					$ids = $model::where('company_id', '=', $this->data['company_id'])->pluck('id')->toArray();
					for($z = 0 ; $z < count($saved_rows) ; $z++){

						if(isset($saved_rows[$z][$this->arranged_object->getJsonColumn()])){
							
							if(in_array($saved_rows[$z][$this->arranged_object->getJsonColumn()], $ids) && $saved_rows[$z]['type'] === 'custom'){
								$temp_saved_rows[] = $saved_rows[$z];
							}
						}else if($saved_rows[$z]['type'] === 'normal'){
							$temp_saved_rows[] = $saved_rows[$z];
						}
					}

					$saved_rows = $temp_saved_rows;
					$temp_saved_rows = null;

				}


				/* filter rows here end */

				$default_merged = array_merge($default_data['rows'], $default_data['dropdown']);
				
				/* check for normal fields */
				for($z = 0 ; $z < count($default_merged) ; $z++){
					
					$found = false;

					for($x = 0 ; $x < count($saved_rows) ; $x++){

						if($default_merged[$z]['type'] === 'normal' || $saved_rows[$x]['type'] === 'normal'){
							
							if($default_merged[$z]['mapped'] == $saved_rows[$x]['mapped']){
								$found = true;
								break;
							}

						}else{
							
							if((string) $saved_rows[$x][$this->arranged_object->getJsonColumn()] === (string) $default_merged[$z][$this->arranged_object->getJsonColumn()]){
								$found = true;
								break;
							}

						}

					}

					if(!$found){
						$dropdown_fields[] = $default_merged[$z];
					}
					
				}
				
				return [
					'dropdown'	=>	$dropdown_fields,
					'rows'		=>	$saved_rows
				];

			}

			return $default_data;
					
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	/**
	 * validateSettingsPost function
	 *
	 * @param array $rows
	 * @param string $model
	 * @param string $table
	 * @return boolean
	 */
	private function validateSettingsPost(array $rows, string $model, string $table) : bool {
		
		$default = $this->arranged_object->fetchDefaultArrangedFieldsData($this->data['company_id']);
		
		$default = array_merge($default['dropdown'], $default['rows']);

		$client_table_columns = Schema::getColumnListing($table);
		
		foreach($rows as $row){
			
			if($row['type'] === 'normal'){
				
				if(!isset($row['mapped'])){
					return false;
				}

				if(!is_array($row['mapped'])){
					return false;
				}
				
				foreach($row['mapped'] as $mapped_col){
					if(!in_array($mapped_col, $client_table_columns)){

						return false;
					}
				}

			}else{
				
				if(!isset($row[$this->arranged_object->getJsonColumn()])){
					return false;
				}
				
				/* if custom fields / columns added */
				$custom_fields_ids = $model::pluck($this->arranged_object->getIdColumn())->toArray();
				
				if(!in_array($row[$this->arranged_object->getJsonColumn()], $custom_fields_ids)){
					return false;
				}
				

			}

		}

		return true;
	}

	/**
	 * validateExceptions function
	 *
	 * @param array $rows
	 * @param array $exceptions
	 * @return string
	 * return '' if nothing found, return text if found.
	 */
	private function validateExceptions(array $rows, array $exceptions) : string {
		
		if(count($exceptions) === 0){
			return '';
		}
		
		$mapped = [];
		foreach($rows as $row){
			if(isset($row['mapped'])){
				if(is_array($row['mapped'])){
					foreach($row['mapped'] as $mapped_field){
						$mapped[] = $mapped_field;
					}
				}
			}
			
		}
		
		foreach($exceptions as $key => $exception){
			if(!in_array($key, $mapped)){
				return $exception;
			}
		}
		return '';
	}

	/**
	 * saveOrUpdate function
	 *
	 * @param string $model
	 * @param string $table
	 * @param array $exceptions
	 * @return void
	 */
	public function saveOrUpdate(string $model, string $table, array $exceptions = []) { /* exceptions text values are mapped to the mapped array for each row */

		try{
			
			$exception_col_name = $this->validateExceptions($this->data['rows'], $exceptions);
			
			if($exception_col_name !== ''){
				return response(['message' => 'You are not allowed to delete '.$exception_col_name,'validity' => 'deletion_not_allowed'], config('global.error_code'));
			}
			
			/* now validate before moving forward */
			if($model !== '' && $table !== ''){
				
				if(!$this->validateSettingsPost($this->data['rows'], $model, $table)){
					return response(['message' => 'invalid request','validity' => 'bad_request'], config('global.error_code'));
				}

			}
			

			$settings = $this->settings_section_repository->fetchSettings((int) $this->data['company_id'], $this->arranged_object->getType());

			if($settings){
				$s = $settings;
			}else{
				$s = $this->settings_section_repository->createObj((int) $this->data['company_id'], $this->arranged_object->getType());
			}

			$json = json_encode($this->data['rows']);

			if($this->settings_section_repository->updateByObj($json, $s)){
				
				return response(['message' => 'Saved successfully','validity' => 'save_success'], 200);
			}

		}catch(Exception $e){
			
			return General::wentWrong();
		}
	}
	
}