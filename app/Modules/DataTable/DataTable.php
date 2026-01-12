<?php

namespace App\Modules\DataTable;

use App\Helpers\Sanitize;
use App\Modules\DataTable\Builders\QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

/**
 * DataTable class
 */
class DataTable{

	/**
	 * __construct function
	 *
	 * @param QueryBuilder $query_builder
	 */
	public function  __construct(private QueryBuilder $query_builder){}

    private string|null $searched_term = null;
    private int|null $current_page = null;
    private array|null $sorted_column = [];
    private int|null $per_page = 15;
    private array|null $date_range = null;
    private array $hide_columns = ['deleted_at', 'updated_at'];
    private bool $paginate = false;
	private Model $model;
	private string $table;
	private array $allowed_columns = [];
	private array $selects;
	private array $joins = [];
	private Builder $fields;
	private array $tables_for_columns = [];
	private array $dates_columns = [];
	private array $rewrites = [];
	private ?array $searchables = null;
	private array $searchable_columns_with_tables = [];
	private array $allowed_sorting_directions = ['asc', 'desc'];
	private ?int $company_id = null;
	
	/**
	 * setVars function
	 *
	 * @param array $data
	 * @return self
	 */
	public function setVars(array $data) : self {
		$this->searched_term = $data['searched_term'];
		$this->current_page = $data['current_page'];
		$this->sorted_column = $data['sorted_column'];

		if($data['per_page']){
			$this->per_page = $data['per_page'];
		}else if($data['default_per_page']){
			$this->per_page = $data['default_per_page'];
		}

		$this->date_range = $data['date_range'];
		return $this;
	}

	/**
	 * setModel function
	 *
	 * @param string $model_class
	 * @return self
	 */
	public function setModel(string $model_class) : self {

		$this->model = new $model_class;
		$this->table = $this->model->getTable();
		$this->allowed_columns = Schema::getColumnListing($this->table);
		return $this;

	}

	/**
	 * setRewrites function
	 *
	 * @param array $rewrites
	 * @return self
	 */
	public function setRewrites(array $rewrites) :self {
		$this->rewrites = $rewrites;
		return $this;
	}

	/**
	 * skipColumns function
	 *
	 * @param array $columns
	 * @return self
	 */
	public function skipColumns(array $columns) : self {
		if(count($columns) > 0){
			$this->allowed_columns = array_values(array_diff($this->allowed_columns, $columns));
		}
		return $this;
	}

	/**
	 * setDatesColumns function
	 *
	 * @param array $dates_columns
	 * @return self
	 */
	public function setDatesColumns(array $dates_columns) : self {
		$this->dates_columns = $dates_columns;
		return $this;
	}

	/**
	 * setCompanyId function
	 *
	 * @param integer $company_id
	 * @return self
	 */
	public function setCompanyId(int $company_id) : self {
		$this->company_id = $company_id;
		
		return $this;
	}

	/**
	 * setSearchableColumns function
	 *
	 * @param array $searchables
	 * @return self
	 */
	public function setSearchableColumns(array $searchables) : self {

		if(!empty($searchables) && $searchables[0] === '*'){
			$this->searchables = null;
		}else{
			$this->searchables = $searchables;
		}
		
		$this->searchable_columns_with_tables = [];
		for($z = 0 ; $z < count($this->allowed_columns) ; $z++){
			$this->tables_for_columns[] = $this->table;
			$this->searchable_columns_with_tables[] = $this->table . '.' . $this->allowed_columns[$z];
		}
		return $this;
	}

	/**
	 * setPaginate function
	 *
	 * @return self
	 */
	public function setPaginate() : self {

		if($this->searched_term || $this->current_page || $this->sorted_column || $this->per_page || $this->date_range){
			$this->paginate = true;
		}

		return $this;

	}

	/**
	 * setPerPage function
	 *
	 * @param integer $per_page
	 * @return self
	 */
	public function setPerPage(int $per_page) : self {
		$this->per_page = $per_page;
		return $this;
	}

	/**
	 * setFields function
	 *
	 * @return self
	 */
	public function setFields() : self {

		$this->fields = $this->query_builder->create($this->model, $this->table);

		$all_columns = Schema::getColumnListing($this->table);
		$this->selects = array_diff($all_columns, $this->hide_columns);

		$this->selects = array_map(function($column) {
			return "{$this->table}.{$column}";
		}, $this->selects);

		return $this;

	}

	/**
	 * setJoins function
	 *
	 * @param array $joins
	 * @return self
	 */
	public function setJoins(array $joins) :self {
		$this->joins = $joins;
		return $this;
	}

	/**
	 * executeCompanyId function
	 *
	 * @return self
	 */
	public function executeCompanyId() : self {
		if($this->company_id !== null){
			$this->fields = $this->query_builder->buildForCompanyId($this->fields, $this->table, $this->company_id);
		}
		return $this;
	}

	/**
	 * executeJoins function
	 *
	 * @return self
	 */
	public function executeJoins() : self {

		if(empty($this->joins)){
			return $this;
		}
		/** works with refs/pointers  */
		$this->query_builder->buildJoins($this->fields, $this->joins, $this->selects, $this->allowed_columns, $this->searchable_columns_with_tables, $this->tables_for_columns);
	
		return $this;

	}

	/**
	 * setPaginateSortedColumns function
	 *
	 * @return self
	 */
	public function setPaginateSortedColumns() : self {

		if($this->sorted_column){

			foreach($this->sorted_column as $key => $value){
				$sorted_column[$key] = Sanitize::input($value);
			}
		}

		return $this;
	}

	/**
	 * executeDateRange function
	 *
	 * @return self
	 */
	public function executeDateRange() : self {

		if($this->date_range){

			if(is_array($this->date_range) && count($this->date_range) === 2){
				
				$from_date = (string) Sanitize::input($this->date_range[0]);
				$to_date = (string) Sanitize::input($this->date_range[1]);

				$data = $this->query_builder->buildForDateRange($this->fields, $from_date, $to_date, $this->dates_columns);
				if($data){
					$this->fields = $data;
				}

			} 

		}
		return $this;
	}

	/**
	 * executeSearchTerm function
	 *
	 * @return self
	 */
	public function executeSearchTerm() : self {

		if($this->searched_term !== ''){

			$this->fields = $this->query_builder->buildForSearchTerm($this->fields, $this->searchable_columns_with_tables, $this->searchables, $this->rewrites, $this->searched_term);
			
		}

		return $this;
	}

	/**
	 * executeRewrites function
	 *
	 * @return self
	 */
	public function executeRewrites() : self {
		if(
			isset($this->sorted_column['label'], $this->sorted_column['sort_visibility']) && 
			in_array($this->sorted_column['label'], $this->allowed_columns, true) && 
			in_array(strtolower($this->sorted_column['sort_visibility']), $this->allowed_sorting_directions, true)
		){

			$this->query_builder->buildForRewrites($this->fields, $this->sorted_column, $this->rewrites, $this->table);

		}

		return $this;

	}

	/**
	 * results function
	 *
	 * @return LengthAwarePaginator
	 */
	public function results() : LengthAwarePaginator {

		$this->setPaginate()->setFields()->executeJoins()->executeCompanyId()->executeDateRange()->executeSearchTerm()->setPaginateSortedColumns()->executeRewrites();
		
		if($this->paginate){
			$fields = $this->fields->orderBy($this->table.'.id', 'desc')->paginate($this->per_page, ['*'], 'page', (int)$this->current_page);
		}else{
			$fields = $this->fields->orderBy($this->table.'.id', 'desc')->paginate($this->per_page, ['*'], 'page', (int)$this->current_page);
		}

		return $fields;

	}
    
}