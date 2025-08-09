<?php

	namespace App\Services;

	use App\Helpers\Sanitize;
	use Illuminate\Support\Facades\Schema;

	class DataTable{

		public static function sortNPaginate($request, $model, $skip_columns = [], $company_id = null){

			$paginate = false;
			$model = new $model;
			
			$table = $model->getTable();

			$allowed_columns = Schema::getColumnListing($table);
			if(count($skip_columns) > 0){
				$allowed_columns = array_values(array_diff($allowed_columns, $skip_columns));
			}

			$allowed_sorting_directions = ['asc', 'desc'];

			if($request->filled('searched_term') || $request->filled('current_page') || $request->filled('sorted_column') || $request->filled('per_page')){
				$paginate = true;
			}

			$default_per_page = (int)Sanitize::input($request->input('default_per_page'));

			if($company_id === null){
				$fields = $model::query();
			}else{
				$fields = $model::where('company_id', '=', $company_id);
			}
			
			
			$current_page = 1;
			$per_page = $default_per_page;

			if($paginate){
				
				$searched_term = '';
				if($request->filled('searched_term')){
					$searched_term = Sanitize::input($request->input('searched_term'));
				}
				
				if($request->filled('per_page')){
					$per_page = (int)Sanitize::input($request->input('per_page'));
				}

				if($request->filled('current_page')){
					$current_page = (int)Sanitize::input($request->input('current_page'));
				}

				$sorted_column = null;
				if($request->filled('sorted_column')){
					$sorted_column = $request->input('sorted_column');
					foreach($sorted_column as $key => $value){
						$sorted_column[$key] = Sanitize::input($value);
					}
				}
				
				if($searched_term !== ''){
					
					
					$fields->where(function ($q) use ($searched_term, $allowed_columns) {
						foreach ($allowed_columns as $index => $column) {
							if($index === 0){
								$q->where($column, 'LIKE', "%$searched_term%");
							}else{
								$q->orWhere($column, 'LIKE', "%$searched_term%");
							}
						}
					});
				}

					
				if(isset($sorted_column['label'], $sorted_column['sort_visibility']) && in_array($sorted_column['label'], $allowed_columns, true) && in_array(strtolower($sorted_column['sort_visibility']), $allowed_sorting_directions, true)){
					$direction = strtolower($sorted_column['sort_visibility']);
					$fields->orderBy($sorted_column['label'], $direction);
				}

			}

			if($paginate){
				$fields = $fields->paginate($per_page, ['*'], 'page', (int)$current_page);
			}else{
				$fields = $fields->orderBy('id', 'desc')->paginate($per_page, ['*'], 'page', (int)$current_page);
			}

			return $fields;

		}

	}