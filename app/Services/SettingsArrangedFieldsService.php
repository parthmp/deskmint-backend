<?php

namespace App\Services;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use App\Models\ClientsCustomField;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SettingsArrangedFieldsService{

	use SettingsDefault;
	
	public function fetchDataToArrangeFields(Request $request, string $type, string $id_column) : Response|array {

		try{

			$company_id = Sanitize::input($request->input('company_id'));

			$default_data = $this->getDefaultInvoiceCompanyDetailsSettings($company_id);

			
			$settings = SettingsSection::where([['type', '=', $type], ['company_id', '=', $company_id]])->first();

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
							
							if($saved_rows[$x][$id_column] === $default_merged[$z][$id_column]){
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

}