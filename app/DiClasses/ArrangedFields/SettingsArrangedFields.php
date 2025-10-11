<?php

namespace App\DiClasses\ArrangedFields;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Interfaces\ArrangedFieldsInterface;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SettingsArrangedFields{

	use SettingsDefault;

	private ArrangedFieldsInterface $arranged_object;
	private int $company_id;
	private Request $request;
	
	public function __construct(ArrangedFieldsInterface $obj, Request $request, int $company_id){
		$this->arranged_object = $obj;
		$this->company_id = $company_id;
		$this->request = $request;
	}

	public function fetchArrangedFieldsData(){

		//try{

			$company_id = Sanitize::input($this->company_id);

			
			$default_data = $this->arranged_object->fetchDefaultArrangedFieldsData($company_id);
			
			
			$settings = SettingsSection::where([['type', '=', $this->arranged_object->getType()], ['company_id', '=', $company_id]])->first();

			if($settings){
				
				$dropdown_fields = [];

				$saved_rows = json_decode($settings->settings_json, true);
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

							if($saved_rows[$x][$this->arranged_object->getJsonColumn()] === $default_merged[$z][$this->arranged_object->getJsonColumn()]){
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
					
		// }catch(Exception $e){
		// 	return General::wentWrong();
		// }

	}

	private function validateSettingsPost(array $rows, string $model, string $table) : bool {
		
		$company_id = Sanitize::input($this->company_id);
			
		$default = $this->arranged_object->fetchDefaultArrangedFieldsData($company_id);

		$default = array_merge($default['dropdown'], $default['rows']);

		foreach($rows as $row){

			if($row['type'] === 'normal'){
				
				if(!isset($row['mapped'])){
					return false;
				}

				if(!is_array($row['mapped'])){
					return false;
				}
				
				$client_table_columns = Schema::getColumnListing($table);
				
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

	public function saveOrUpdate(string $model, string $table) {
		
		$v = Validator::make($this->request->all(), [
			'rows'              => 'required|array',
			'rows.*.id'         => 'required|integer',
			'rows.*.text'       => 'required|string',
			'rows.*.value'      => 'required|string',
			'rows.*.type'       => 'required|string|in:normal,custom'
		]);

		if($v->fails()){
			return response(['message' => 'invalid request','validity' => 'invalid_data'], config('global.error_code'));
		}

		try{

			$rows = $this->request->input('rows');
			
			/* now validate before moving forward */
			if(!$this->validateSettingsPost($rows, $model, $table)){
				return response(['message' => 'invalid request','validity' => 'bad_request'], config('global.error_code'));
			}

			$settings = SettingsSection::where([['type', '=', $this->arranged_object->getType()], ['company_id', '=', $this->company_id]])->first();

			if($settings){
				$s = $settings;
			}else{
				$s = new SettingsSection();
				$s->company_id = $this->company_id;
				$s->type = $this->arranged_object->getType();
			}

			$s->settings_json = json_encode($rows);

			if($s->save()){
				return response(['message' => 'Saved successfully','validity' => 'save_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}
	}
	
}