<?php

namespace App\Modules\DataTable;

use App\Modules\DataTable\Exceptions\DataTableException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DataTable{

    private Builder $query;
    
    private array $config = [];
    private array $joins = [];
    private array $rewrites = [];
    private array $searchable_columns = [];
    private array $date_columns = [];
    private array $skip_columns = [];
	
    private string|null $search_term = null;
    private string|null $sort_column = null;
    private string|null $sort_direction = null;
    private string|null $date_from = null;
    private string|null $date_to = null;
    
    private int $per_page = 15;
    private int $current_page = 1;
    private int $company_id;
    private bool $should_paginate = false;

	public function __construct(){
		$this->loadConfig();
	}

	private function loadConfig(): void {

        $this->config = config('datatable', [
            'default_per_page' 			=> 15,
            'hide_columns' 				=> ['deleted_at', 'updated_at'],
            'allowed_sort_directions' 	=> ['asc', 'desc'],
			'search_operator' 			=> 'LIKE'
        ]);
        
        $this->per_page = $this->config['default_per_page'];
    }

	private function validateJoin(array $join): void {

        $required = ['table', 'first', 'operator', 'second'];
        
        foreach($required as $field){
            if(!isset($join[$field])){
                throw new DataTableException("Join array must contain '{$field}' key.");
            }
        }

    }

	public function setSkipColumns(array $columns): self {
        $this->skip_columns = $columns;
        return $this;
    }

	public function addJoin(array $join): self {
        $this->validateJoin($join);
        $this->joins[] = $join;
        return $this;
    }

	private function initializeColumnsFromTable(string $table): void {

        $columns = Schema::getColumnListing($table);
        
		if(count($this->skip_columns) > 0){
			$columns = array_diff($columns, $this->skip_columns, $this->config['hide_columns']);
		}

        $this->searchable_columns = $columns;

    }
    
    
    public function setModel(string $model_class): self {

        if(!class_exists($model_class)){
            throw new DataTableException("Model class {$model_class} does not exist.");
        }
        
        $model = new $model_class;
        $this->query = $model->newQuery();
        
        return $this;
    }
    
    public function setTable(string $table): self {
		$this->query = DB::table($table);
		$this->initializeColumnsFromTable($table);
        return $this;
    }
    
    public function getQuery(): Builder {
        return $this->query;
    }

	public function setSearchTerm(string|null $term) : self {
		$this->search_term = $term;
		$this->should_paginate = true;
		return $this;
	}

	public function setCurrentPage(int $page): self {
        $this->current_page = $page;
        $this->should_paginate = true;
        return $this;
    }
    
    public function sortAndPaginate(array $params = []){
        
    }
    
    
}