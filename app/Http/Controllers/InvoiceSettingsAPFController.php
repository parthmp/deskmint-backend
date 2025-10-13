<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalProductColumnsField;
use App\Models\SettingsSection;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsAPFController extends Controller{

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		try{

			$fields = AdditionalProductColumnsField::where('company_id', '=', $company_id)->get();

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

		}catch(Exception $e){
			return General::wentWrong();
		}

	}
    
	public function saveOrUpdate(Request $request){

		$v = Validator::make($request->all(), [
			'labels'			=>	'required|array',
			'types'				=>	'required|array',
			'taxes'				=>	'required|array',
			'labels.*.value'	=>	'required',
			'types.*.value'		=>	'required',
			'taxes.*.value'		=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

		$labels = $request->input('labels');
		$types = $request->input('types');
		$taxes = $request->input('taxes');
		$company_id = Sanitize::input($request->input('company_id'));
		
		try{

			$invalid_ids = false;

			$upsert = [];
			/* now validate for ids */
			for($z = 0 ; $z < count($labels) ; $z++){

				if($labels[$z]['id'] !== $types[$z]['id'] || $labels[$z]['id'] !== $taxes[$z]['id'] || $types[$z]['id'] !== $taxes[$z]['id']){
					$invalid_ids = true;
					break;
				}

				$upsert[] = [
					'id'				=>	$labels[$z]['id'],
					'company_id'		=>	$company_id,
					'label'				=>	 Sanitize::input($labels[$z]['value'].''),
					'type'				=>	 Sanitize::input($types[$z]['value'].''),
					'tax_rate'			=>	 (float) Sanitize::input($taxes[$z]['value']),
				];

			}

			if($invalid_ids){
				return response(['message' => 'Invalid request', 'validity' => 'invalid_ids'], config('global.error_code'));
			}

			AdditionalProductColumnsField::upsert($upsert, ['id'], ['label', 'type', 'tax_rate']);

			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function destroy(Request $request, int $id){

		try{

			$id = Sanitize::input($id);
			$company_id = Sanitize::input($request->input('company_id'));

			AdditionalProductColumnsField::where('id', '=', $id)->delete();

			/* modify json here for sorting */
			$settings = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', ISC_PRODUCT_COLUMNS_TYPE]])->first();
			
			if($settings){

				$modified = [];

				$json = json_decode($settings->settings_json, true);
				
				for($z = 0 ; $z < count($json) ; $z++){
					if($json[$z]['type'] === 'custom'){
						
						if($json[$z]['id_column'] !== (int) $id){
							$modified[] = $json[$z];
						}
					}else{
						$modified[] = $json[$z];
					}
				}
				
				$settings->settings_json = json_encode($modified);
				$settings->save();

			}

			return response(['message' => 'Removed successfully', 'validity' => 'delete_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
