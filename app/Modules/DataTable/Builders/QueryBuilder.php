<?php

namespace App\Modules\DataTable\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QueryBuilder{

	/**
	 * create function
	 *
	 * @param Model $model
	 * @param string $table
	 * @return Builder
	 */
	public function create(Model $model, string $table) : Builder {
		return $model::query()->from($table);
	}

	/**
	 * buildForCompanyId function
	 *
	 * @param Builder $fields
	 * @param string $table
	 * @param integer $company_id
	 * @return Builder
	 */
	public function buildForCompanyId(Builder $fields, string $table, int $company_id) : Builder {
		return $fields->where("{$table}.company_id", '=', $company_id);
	}

	/**
	 * buildJoins function
	 *
	 * @param Builder $fields
	 * @param array $joins
	 * @param array $selects
	 * @param array $allowed_columns
	 * @param array $searchable_columns_with_tables
	 * @param array $tables_for_columns
	 * @return void
	 */
	public function buildJoins(Builder &$fields, array &$joins, array &$selects, array &$allowed_columns, array &$searchable_columns_with_tables, array &$tables_for_columns) : void {

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
		}

		$fields = $fields->select($selects);

	}

	/**
	 * buildForDateRange function
	 *
	 * @param Builder $fields
	 * @param string $from_date
	 * @param string $to_date
	 * @param array $dates_columns
	 * @return Builder|null
	 */
	public function buildForDateRange(Builder $fields, string $from_date, string $to_date, array $dates_columns) : Builder|null {

		if(strtotime($from_date) !== false && strtotime($to_date) !== false){

			return $fields->where(function ($query) use ($from_date, $to_date, $dates_columns) {
				foreach($dates_columns as $index => $column){
					if($index === 0){
						$query->whereBetween($column, [$from_date, $to_date]);
					}else{
						$query->orWhereBetween($column, [$from_date, $to_date]);
					}
				}
			});

		}


		return null;

	}

	/**
	 * buildForSearchTerm function
	 *
	 * @param Builder $fields
	 * @param array $searchable_columns_with_tables
	 * @param array $searchables
	 * @param array $rewrites
	 * @param string|null $searched_term
	 * @return Builder
	 */
	public function buildForSearchTerm(Builder $fields, array $searchable_columns_with_tables, ?array $searchables, array $rewrites, ?string $searched_term) : Builder {
		
		return $fields->where(function ($q) use($searchable_columns_with_tables, $searchables, $rewrites, $searched_term) {
				
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

	}

	/**
	 * buildForRewrites function
	 *
	 * @param Builder $fields
	 * @param array $sorted_column
	 * @param array $rewrites
	 * @param string $table
	 * @return void
	 */
	public function buildForRewrites(Builder &$fields, array $sorted_column, array $rewrites, string $table){

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