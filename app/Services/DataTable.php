<?php

	namespace App\Services;

	use App\Helpers\Sanitize;
	use Illuminate\Support\Facades\Schema;

	class DataTable{

		public static function sortNPaginate($request, $model, $skip_columns = [], $company_id = null, $joins = [], $rewrites = []){

			$hide_columns = ['searchable_created_at', 'deleted_at', 'updated_at'];

			$paginate = false;
			$model = new $model;
			
			$table = $model->getTable();

			$allowed_columns = Schema::getColumnListing($table);
			$tables_for_columns = [];
			if(count($skip_columns) > 0){
				$allowed_columns = array_values(array_diff($allowed_columns, $skip_columns));
			}

			for($z = 0 ; $z < count($allowed_columns) ; $z++){
				$tables_for_columns[] = $table;
			}

			$allowed_sorting_directions = ['asc', 'desc'];

			if($request->filled('searched_term') || $request->filled('current_page') || $request->filled('sorted_column') || $request->filled('per_page')){
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
						
						/* Extract alias if present */
						if(stripos($col, ' as ') !== false) {
							[, $alias] = preg_split('/\s+as\s+/i', $col);
							$allowed_columns[] = trim($alias);
						}else{
							$allowed_columns[] = basename(str_replace('.', '/', $col));
						}

						$tables_for_columns[] = $join['table'];
					}
				}
			}
			
			$fields->select($selects);
			
			if ($company_id !== null) {
				$fields->where("{$table}.company_id", '=', $company_id);
			}
			
			$modified_columns_for_tables = [];
			for($z = 0 ; $z < count($allowed_columns) ; $z++){
				$modified_columns_for_tables[$z] = $tables_for_columns[$z].'.'.$allowed_columns[$z];
			}
			
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
				
				if($searched_term !== ''){
					
					$fields->where(function ($q) use ($searched_term, $modified_columns_for_tables, $rewrites){
						foreach ($modified_columns_for_tables as $index => $column){

							$search_expr = $column;

							foreach ($rewrites as $key => $map){
								if ($column === $key || $column === $key || $column === preg_replace('/.*\./', '', $key)){
									$case = "CASE";
									foreach($map as $db_value => $display_value){
										$case .= " WHEN {$key} = '".addslashes($db_value)."' THEN '".addslashes($display_value)."'";
									}
									$case .= " ELSE {$key} END";
									$search_expr = \DB::raw($case);
									break;
								}
							}

							if ($index === 0) {
								$q->whereRaw($search_expr instanceof \Illuminate\Database\Query\Expression ? $search_expr->getValue(\DB::connection()->getQueryGrammar()) . " LIKE ?" : "{$search_expr} LIKE ?", ["%{$searched_term}%"]);
							} else {
								$q->orWhereRaw($search_expr instanceof \Illuminate\Database\Query\Expression ? $search_expr->getValue(\DB::connection()->getQueryGrammar()) . " LIKE ?" : "{$search_expr} LIKE ?", ["%{$searched_term}%"]
								);
							}
						}
					});
				}

				if (isset($sorted_column['label'], $sorted_column['sort_visibility']) && in_array($sorted_column['label'], $allowed_columns, true) && in_array(strtolower($sorted_column['sort_visibility']), $allowed_sorting_directions, true)){

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
						$fields->orderBy($column, $direction);
					}
				}

			}

			if($paginate){
				$fields = $fields->orderBy($table.'.id', 'desc')->paginate($per_page, ['*'], 'page', (int)$current_page);
			}else{
				$fields = $fields->orderBy($table.'.id', 'desc')->paginate($per_page, ['*'], 'page', (int)$current_page);
			}

			return $fields;

		}

	}
