<?php

namespace App\Modules\DataTable;

use App\Helpers\Sanitize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataTable{

    private string|null $searched_term = null;
    private int|null $current_page = null;
    private array|null $sorted_column = [];
    private string|null $per_page = null;
    private array|null $date_range = null;
    private int $default_per_page = 15;
    private array $hide_columns = ['deleted_at', 'updated_at'];
    private bool $paginate = false;
	private Model $model;
	private string $table;
	private array $allowed_columns;
	private array $selects;
	private array $joins;
	private Builder $fields;
	private array $tables_for_columns = [];
	private array $dates_columns = [];
	private array $rewrites = [];
	private array $searchables = [];
	private array $searchable_columns_with_tables = [];
	private array $allowed_sorting_directions = ['asc', 'desc'];
	private int $company_id = 0;
	
	public function setVars(array $data) : self {
		$this->searched_term = $data['searched_term'];
		$this->current_page = $data['current_page'];
		$this->sorted_column = $data['sorted_column'];
		$this->per_page = $data['per_page'];
		$this->date_range = $data['date_range'];
		return $this;
	}

	public function setModel(string $model_class) : self {

		$this->model = new $model_class;
		$this->table = $this->model->getTable();
		$this->allowed_columns = Schema::getColumnListing($this->table);
		return $this;

	}

	public function skipColumns(array $columns) : self {
		if(count($columns) > 0){
			$this->allowed_columns = array_values(array_diff($this->allowed_columns, $columns));
		}
		return $this;
	}

	public function setDatesColumns(array $dates_columns) : self {
		$this->dates_columns = $dates_columns;
		return $this;
	}

	public function setCompanyId(int $company_id) : self {
		$this->company_id = $company_id;
		return $this;
	}

	public function setSearchableColumns() : self {
		$this->searchable_columns_with_tables = [];
		for($z = 0 ; $z < count($this->allowed_columns) ; $z++){
			$tables_for_columns[] = $this->table;
			$this->searchable_columns_with_tables[] = $this->table . '.' . $this->allowed_columns[$z];
		}
		return $this;
	}

	public function setPaginate() : self {

		if($this->searched_term || $this->current_page || $this->sorted_column || $this->per_page || $this->date_range){
			$this->paginate = true;
		}

		return $this;

	}

	public function setPerPage(int $per_page) : self {
		$this->default_per_page = $per_page;
		return $this;
	}

	public function setFields() : self {

		$this->fields = $this->model::query()->from($this->table);

		$all_columns = Schema::getColumnListing($this->table);
		$this->selects = array_diff($all_columns, $this->hide_columns);

		$this->selects = array_map(function($column) {
			return "{$this->table}.{$column}";
		}, $this->selects);

		return $this;

	}

	public function setJoins(array $joins) :self {
		$this->joins = $joins;
		return $this;
	}

	public function executeJoins() : self {

		foreach($this->joins as $join){

			$this->fields->leftJoin($join['table'], $join['first'], $join['operator'], $join['second']);

			if(!empty($join['columns'])){

				foreach ($join['columns'] as $col){

					$this->selects[] = $col;
					
					if(stripos($col, ' as ') !== false) {
						[$actual_column, $alias] = preg_split('/\s+as\s+/i', $col);
						$allowed_columns[] = trim($alias);
						$this->searchable_columns_with_tables[] = trim($actual_column);
					}else{
						$allowed_columns[] = basename(str_replace('.', '/', $col));
						$this->searchable_columns_with_tables[] = $col;
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

		$this->fields->select($this->selects);

		return $this;

	}

	public function setPaginateSortedColumns() : self {

		// $searched_term = '';
		// if($this->searched_term){
		// 	$this->searched_term = Sanitize::input($request->input('searched_term'));
		// }
		
		// if($request->filled('per_page')){
		// 	$per_page = (int)Sanitize::input($request->input('per_page'));
		// }

		// if($request->filled('current_page')){
		// 	$current_page = (int)Sanitize::input($request->input('current_page'));
		// }

		//$this->sorted_column = null;
		if($this->sorted_column){
			
			//$sorted_column = $request->input('sorted_column');
			
			foreach($this->sorted_column as $key => $value){
				$sorted_column[$key] = Sanitize::input($value);
			}
		}

		return $this;
	}

	public function setForDateRange() : self {

		if($this->date_range){

			if(is_array($this->date_range) && count($this->date_range) === 2){
				
				$from_date = (string) Sanitize::input($this->date_range[0]);
				$to_date = (string) Sanitize::input($this->date_range[1]);
				
				if(strtotime($from_date) !== false && strtotime($to_date) !== false){

					$this->fields = $this->fields->where(function ($query) use ($from_date, $to_date) {
						foreach($this->dates_columns as $index => $column){
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
		return $this;
	}

	public function executeSearchTerm() : self {

		if($this->searched_term !== ''){
					
			//if(empty($searchables)){
				$this->fields = $this->fields->where(function ($q) {
					
					foreach($this->searchable_columns_with_tables as $index => $column){

						$search_allowed = false;

						if($this->searchables === null){
							$search_allowed = true;
						}else if(is_array($this->searchables)){
							if(in_array($column, $this->searchables)){
								$search_allowed = true;
							}
						}

						if($search_allowed){

							$search_expr = $column;

							foreach($this->rewrites as $key => $map){
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
								$q->whereRaw($search_expr instanceof \Illuminate\Database\Query\Expression ? $search_expr->getValue(DB::connection()->getQueryGrammar()) . " LIKE ?" : "{$search_expr} LIKE ?", ["%{$this->searched_term}%"]);
							}else{
								$q->orWhereRaw($search_expr instanceof \Illuminate\Database\Query\Expression ? $search_expr->getValue(DB::connection()->getQueryGrammar()) . " LIKE ?" : "{$search_expr} LIKE ?", ["%{$this->searched_term}%"]
								);
							}

						}
					
					}
				});
			//}

		}

		return $this;
	}

	public function executeRewrites() : self {
		if(isset($this->sorted_column['label'], $this->sorted_column['sort_visibility']) && in_array($this->sorted_column['label'], $this->allowed_columns, true) && in_array(strtolower($this->sorted_column['sort_visibility']), $this->allowed_sorting_directions, true)){

			$direction = strtolower($this->sorted_column['sort_visibility']);
			$column = $this->sorted_column['label'];

			$rewrite_key = null;
			foreach($this->rewrites as $key => $map){
				if($column === $key || $column === preg_replace('/.*\./', '', $key)){
					$rewrite_key = $key;
					break;
				}
			}

			if($rewrite_key !== null){

				$case = "CASE";
				foreach($this->rewrites[$rewrite_key] as $db_value => $display_value){
					$case .= " WHEN {$rewrite_key} = '".addslashes($db_value)."' THEN '".addslashes($display_value)."'";
				}
				$case .= " ELSE {$rewrite_key} END";

				$this->fields->orderByRaw("$case $direction");

			}else{

				
				$database_type = DB::connection()->getConfig('driver');
				if($database_type === 'mysql'){
					$this->fields->orderBy($column, $direction);
				}else{
					$qualified_column = (strpos($column, '.') === false) ? "{$this->table}.{$column}" : $column;
					$this->fields->orderBy($qualified_column, $direction);
				}
				

			}
		}

		return $this;

	}

	public function results() : LengthAwarePaginator {

		$this->setSearchableColumns()->setPaginate()->setFields()->executeJoins()->setForDateRange()->executeSearchTerm()->setPaginateSortedColumns()->executeRewrites();

		if($this->paginate){
			$fields = $this->fields->orderBy($this->table.'.id', 'desc')->paginate($this->per_page, ['*'], 'page', (int)$this->current_page);
		}else{
			$fields = $this->fields->orderBy($this->table.'.id', 'desc')->paginate($this->per_page, ['*'], 'page', (int)$this->current_page);
		}

		return $fields;

	}
    
}