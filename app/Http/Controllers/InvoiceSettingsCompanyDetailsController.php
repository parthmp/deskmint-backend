<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use App\Models\SettingsSection;
use App\Services\SettingsArrangedFieldsService;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsCompanyDetailsController extends Controller{
    
	use SettingsDefault;

	public function show(Request $request) : mixed{

		return (new SettingsArrangedFieldsService())->fetchDataToArrangeFields($request, 'invoice_company_details', 'id');

	}

	private function validateClientSettingsPost(int $company_id, array $rows) : bool{

		$default = $this->getDefaultInvoiceCompanyDetailsSettings($company_id);
		$default = array_merge($default['dropdown'], $default['rows']);

		foreach($rows as $row){

			if($row['type'] === 'normal'){
				
				if(!isset($row['mapped'])){
					return false;
				}

				if(!is_array($row['mapped'])){
					return false;
				}

				$client_table_columns = Schema::getColumnListing('companies');
				foreach($row['mapped'] as $mapped_col){
					if(!in_array($mapped_col, $client_table_columns)){
						return false;
					}
				}

			}else{

				if(!isset($row['id_column'])){
					return false;
				}
				
				/* if custom fields / columns added */
				$custom_fields_ids = AdditionalCompanyField::pluck('id')->toArray();
				if(!in_array($row['id_column'], $custom_fields_ids)){
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

		//try{

			$rows = $request->input('rows');

			$company_id = Sanitize::input($request->input('company_id'));

			/* now validate before moving forward */
			if(!$this->validateClientSettingsPost($company_id, $rows)){
				return response(['message' => 'invalid request','validity' => 'bad_request'], config('global.error_code'));
			}

			$settings = SettingsSection::where([['type', '=', 'invoice_company_details'], ['company_id', '=', $company_id]])->first();

			if($settings){
				$s = $settings;
			}else{
				$s = new SettingsSection();
				$s->company_id = $company_id;
				$s->type = 'invoice_company_details';
			}

			$s->settings_json = json_encode($rows);

			if($s->save()){
				return response(['message' => 'Saved successfully','validity' => 'save_success'], 200);
			}

		// }catch(Exception $e){
		// 	return General::wentWrong();
		// }

	}

}
