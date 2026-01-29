<?php

namespace App\Modules\ArrangedDataTableColumns\Actions;

use App\Helpers\Sanitize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class Save{

	public function __construct(private Validation $validation){}

	public function saveArrangedColumnsData(Request $request, string $custom_fields_model, string $feature_name, string $original_table, string $type) : Response{
		
		$validated = $this->validation->validatePostedColumns($request);
		if($validated !== null){
			return $validated;
		}

		$columns = $request->input('columns');
		$company_id = Sanitize::input($request->input('company_id'));

		if(empty($columns)){
			UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->delete();
			//return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}

		$general_columns = Schema::getColumnListing($original_table);
		$general_columns = array_values(array_diff($general_columns, ['deleted_at', 'updated_at']));
		
		$general_custom_column_ids = $custom_fields_model::where('company_id', '=', $company_id)->pluck('id')->toArray();

		for($z = 0 ; $z < count($columns) ; $z++){
			if(!isset($columns[$z][$type.'s_custom_fields_id'])){
				$columns[$z][$type.'s_custom_fields_id'] = '-';
			}
			if(!in_array($columns[$z]['label'], $general_columns) && !in_array($columns[$z][$type.'s_custom_fields_id'], $general_custom_column_ids)){
				//return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
			}

		}
		
		try{

			$columns = json_encode($columns);
			
			UserIndexColumn::where([['user_id', '=', Auth::user()->id], ['company_id', '=', $company_id], ['feature_name', '=', $feature_name]])->delete();
			
			$user_index_col = new UserIndexColumn();
			$user_index_col->user_id = Auth::user()->id;
			$user_index_col->company_id = $company_id;
			$user_index_col->feature_name = $feature_name;
			$user_index_col->columns_json = $columns;
			$user_index_col->save();
			//return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(Exception $e){
			//return General::wentWrong();
		}
	}

}