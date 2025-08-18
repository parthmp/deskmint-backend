<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use Illuminate\Http\Request;

class ClientsController extends Controller{

	public function fetchClientsCustomFields(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('id', 'asc')->with('customFieldType')->get();

		return $this->adjustRowsPrinting($fields);

	}

	private function adjustRowsPrinting($fields){ 

		$full_width_types = [
			config('global.field_types')[1],
			config('global.field_types')[9]
		];

		$rows = [];
		$current_row = [];

		foreach($fields as $field){

			$current_type = $field->customFieldType->input_type;

			if(in_array($current_type, $full_width_types)){
				
				if(!empty($current_row)){
					$rows[] = $current_row;
					$current_row = [];
				}
				
				$rows[] = [$field];

			}else{

				$current_row[] = $field;
				if(count($current_row) == 3){
					$rows[] = $current_row;
					$current_row = [];
				}
			
			}

		}

		if(!empty($current_row)){
			$rows[] = $current_row;
		}

		foreach($rows as $row){

			$count = count($row);
			$span = 12;
			
			if($count === 2){
				$span = 6;
			}
			
			if($count === 3){
				$span = 4;
			}
			
			foreach ($row as $field) {
				$field->span = $span; 
			}

		}
		
		return collect($rows)->flatten();

	}
	
}
