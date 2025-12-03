<?php

	namespace App\Services;

	use App\Helpers\Sanitize;
	use App\Models\ClientsCustomField;
	use Illuminate\Support\Facades\Schema;
	use Carbon\Carbon;
	use Carbon\CarbonTimeZone;
	use Illuminate\Support\Facades\DB;

	class DataTable{

		public static function sortNPaginate($request, $model, $skip_columns = [], $company_id = null, $dates_columns = [], $joins = [], $rewrites = [], $searchables = null){

			$hide_columns = ['deleted_at', 'updated_at'];

			$paginate = false;
			$model = new $model;
			
			$table = $model->getTable();

			$allowed_columns = Schema::getColumnListing($table);
			$tables_for_columns = [];
			if(count($skip_columns) > 0){
				$allowed_columns = array_values(array_diff($allowed_columns, $skip_columns));
			}

			$searchable_columns_with_tables = [];
			for($z = 0 ; $z < count($allowed_columns) ; $z++){
				$tables_for_columns[] = $table;
				$searchable_columns_with_tables[] = $table . '.' . $allowed_columns[$z];
			}

			$allowed_sorting_directions = ['asc', 'desc'];

			if($request->filled('searched_term') || $request->filled('current_page') || $request->filled('sorted_column') || $request->filled('per_page') || $request->filled('date_range')){
				$paginate = true;
			}

			$default_per_page = (int)Sanitize::input($request->input('default_per_page'));

		
			$fields = $model::query()->from($table);

			/* joins for relative tables */
			/*$selects = ["{$table}.*"];*/
			/* hide columns in response */
			$all_columns = Schema::getColumnListing($table);
			$selects = array_diff($all_columns, $hide_columns);

			$selects = array_map(function($column) use ($table) {
				return "{$table}.{$column}";
			}, $selects);
			
			foreach($joins as $join){
				$fields->leftJoin($join['table'], $join['first'], $join['operator'], $join['second']);

				if(!empty($join['columns'])){

					foreach ($join['columns'] as $col){

						$selects[] = $col;
						
						if(stripos($col, ' as ') !== false) {
							[$actual_column, $alias] = preg_split('/\s+as\s+/i', $col);
							$allowed_columns[] = trim($alias);
							$searchable_columns_with_tables[] = trim($actual_column);
						}else{
							$allowed_columns[] = basename(str_replace('.', '/', $col));
							$searchable_columns_with_tables[] = $col;
						}

						if(stripos($join['table'], ' as ') !== false){
							[, $alias] = preg_split('/\s+as\s+/i', $join['table']);
							$tables_for_columns[] = trim($alias);
						}else{
							$tables_for_columns[] = $join['table'];
						}
					}

				}

				// if(!empty($join['columns'])){
				// 	foreach ($join['columns'] as $col){
						
				// 		$selects[] = $col;
						
				// 		/* Extract alias if present */
				// 		if(stripos($col, ' as ') !== false) {
				// 			[, $alias] = preg_split('/\s+as\s+/i', $col);
				// 			$allowed_columns[] = trim($alias);
				// 		}else{
				// 			$allowed_columns[] = basename(str_replace('.', '/', $col));
				// 		}

				// 		if(stripos($join['table'], ' as ') !== false){
				// 			[, $alias] = preg_split('/\s+as\s+/i', $join['table']);
				// 			$tables_for_columns[] = trim($alias);
				// 		}else{
				// 			$tables_for_columns[] = $join['table'];
				// 		}
				// 	}
				// }
			}
			
			$fields->select($selects);

			/**/
			
			if ($company_id !== null) {
				$fields->where("{$table}.company_id", '=', $company_id);
			}
			
			// $modified_columns_for_tables = [];
			// for($z = 0 ; $z < count($allowed_columns) ; $z++){
			// 	$modified_columns_for_tables[$z] = $tables_for_columns[$z].'.'.$allowed_columns[$z];
			// }
			
			$tables_for_columns = null;

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

				if($request->filled('date_range')){

					$date_range = $request->input('date_range');
					

					if(is_array($date_range) && count($date_range) === 2){
						
						$from_date = (string) Sanitize::input($date_range[0]);
						$to_date = (string) Sanitize::input($date_range[1]);
						
						if(strtotime($from_date) !== false && strtotime($to_date) !== false){

							$fields = $fields->where(function ($query) use ($dates_columns, $from_date, $to_date) {
								foreach($dates_columns as $index => $column){
									if($index === 0){
										$query->whereBetween($column, [$from_date, $to_date]);
									}else{
										$query->orWhereBetween($column, [$from_date, $to_date]);
									}
								}
							});

						}
					} 

				}
				
				if($searched_term !== ''){
					
					//if(empty($searchables)){
						$fields->where(function ($q) use ($searched_term, $searchable_columns_with_tables, $rewrites, $searchables){
							
							foreach($searchable_columns_with_tables as $index => $column){

								$search_allowed = false;

								if($searchables === null){
									$search_allowed = true;
								}else if(is_array($searchables)){
									if(in_array($column, $searchables)){
										$search_allowed = true;
									}
								}

								if($search_allowed){

									$search_expr = $column;

									foreach($rewrites as $key => $map){
										if($column === $key || $column === $key || $column === preg_replace('/.*\./', '', $key)){
											$case = "CASE";
											foreach($map as $db_value => $display_value){
												$case .= " WHEN {$key} = '".addslashes($db_value)."' THEN '".addslashes($display_value)."'";
											}
											$case .= " ELSE {$key} END";
											$search_expr = DB::raw($case);
											break;
										}
									}

									if($index === 0){
										$q->whereRaw($search_expr instanceof \Illuminate\Database\Query\Expression ? $search_expr->getValue(DB::connection()->getQueryGrammar()) . " LIKE ?" : "{$search_expr} LIKE ?", ["%{$searched_term}%"]);
									}else{
										$q->orWhereRaw($search_expr instanceof \Illuminate\Database\Query\Expression ? $search_expr->getValue(DB::connection()->getQueryGrammar()) . " LIKE ?" : "{$search_expr} LIKE ?", ["%{$searched_term}%"]
										);
									}

								}
							
							}
						});
					//}

				}

				if(isset($sorted_column['label'], $sorted_column['sort_visibility']) && in_array($sorted_column['label'], $allowed_columns, true) && in_array(strtolower($sorted_column['sort_visibility']), $allowed_sorting_directions, true)){

					$direction = strtolower($sorted_column['sort_visibility']);
					$column = $sorted_column['label'];

					$rewrite_key = null;
					foreach($rewrites as $key => $map){
						if($column === $key || $column === preg_replace('/.*\./', '', $key)){
							$rewrite_key = $key;
							break;
						}
					}

					if($rewrite_key !== null){

						$case = "CASE";
						foreach($rewrites[$rewrite_key] as $db_value => $display_value){
							$case .= " WHEN {$rewrite_key} = '".addslashes($db_value)."' THEN '".addslashes($display_value)."'";
						}
						$case .= " ELSE {$rewrite_key} END";

						$fields->orderByRaw("$case $direction");

					}else{
	
    					
						$database_type = DB::connection()->getConfig('driver');
						if($database_type === 'mysql'){
							$fields->orderBy($column, $direction);
						}else{
							$qualified_column = (strpos($column, '.') === false) ? "{$table}.{$column}" : $column;
							$fields->orderBy($qualified_column, $direction);
						}
						

					}
				}

			}
			// if(isset($join['raw'])){
			// 	$fields->groupBy('clients.id');
				
			// }
			if($paginate){
				$fields = $fields->orderBy($table.'.id', 'desc')->paginate($per_page, ['*'], 'page', (int)$current_page);
			}else{
				$fields = $fields->orderBy($table.'.id', 'desc')->paginate($per_page, ['*'], 'page', (int)$current_page);
			}

			return $fields;

		}

		public static function modifyForColumns($columns, $columns_sorting){
			if(empty($columns_sorting)){
				return $columns;
			}
			
			$result = [];
			
			if(!empty($columns_sorting->custom_fields)){
				
				$custom_field_ids = array_column($columns_sorting->custom_fields, 'clients_custom_fields_id');
				
				$custom_fields = ClientsCustomField::whereIn('id', $custom_field_ids)->with('customFieldType')->get()->keyBy('id');
				
				foreach($columns_sorting->custom_fields as $item){

					$field = $custom_fields[$item->clients_custom_fields_id] ?? null;
					$input_type = $field->customFieldType->input_type ?? null;

					$label = str_replace([' ', '-'], '_', strtolower($field->label ?? ''));
					if($input_type === 'date'){
						$label .= '_cdate_';
					}
					
					$result[] = [
						'label' => $label,
						'text' => $field->label ?? null,
						'order' => $item->order
					];
				}
			}
			
			if(!empty($columns_sorting->client_fields)){
				foreach($columns_sorting->client_fields as $item){
					$result[] = [
						'label' => $item->field,
						'text' => ucwords(str_replace('_', ' ', $item->field)),
						'order' => $item->order
					];
				}
			}
			
			usort($result, function($a, $b){
				return $a['order'] <=> $b['order'];
			});
			
			unset($item);
			
			array_push($result, [
						'label'	=> 'actions',
						'text'	=> 'Actions'
					]);

			return $result;
		}

	}
