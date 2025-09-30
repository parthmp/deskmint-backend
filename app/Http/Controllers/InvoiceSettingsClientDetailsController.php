<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsClientDetailsController extends Controller{

	use SettingsDefault;

	public function show(Request $request) : mixed{

		//try{

			$default_data = $this->getDefaultInvoiceClientDetailsSettings();

			$company_id = Sanitize::input($request->input('company_id'));

			$settings = SettingsSection::where([['type', '=', 'invoice_client_details'], ['company_id', '=', $company_id]])->first();

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
							
							if($saved_rows[$x]['clients_custom_fields_id'] === $default_merged[$z]['clients_custom_fields_id']){
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

	private function validateClientSettingsPost(array $rows) : bool{

		$default = $this->getDefaultInvoiceClientDetailsSettings();
		$default = array_merge($default['dropdown'], $default['rows']);

		foreach($rows as $row){

			if($row['type'] === 'normal'){
				
				if(!isset($row['mapped'])){
					return false;
				}

				if(!is_array($row['mapped'])){
					return false;
				}

				$client_table_columns = Schema::getColumnListing('clients');
				foreach($row['mapped'] as $mapped_col){
					if(!in_array($mapped_col, $client_table_columns)){
						return false;
					}
				}

			}else{

				if(!isset($row['clients_custom_fields_id'])){
					return false;
				}
				
				/* if custom fields / columns added */
				$custom_fields_ids = ClientsCustomField::pluck('id')->toArray();
				if(!in_array($row['clients_custom_fields_id'], $custom_fields_ids)){
					return false;
				}
				

			}

		}

		return true;
	}

	public function saveOrUpdate(Request $request){
		
		$v = Validator::make($request->all(), [
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

			$rows = $request->input('rows');

			/* now validate before moving forward */
			if(!$this->validateClientSettingsPost($rows)){
				return response(['message' => 'invalid request','validity' => 'bad_request'], config('global.error_code'));
			}


			$company_id = Sanitize::input($request->input('company_id'));

			$settings = SettingsSection::where([['type', '=', 'invoice_client_details'], ['company_id', '=', $company_id]])->first();

			if($settings){
				$s = $settings;
			}else{
				$s = new SettingsSection();
				$s->company_id = $company_id;
				$s->type = 'invoice_client_details';
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
