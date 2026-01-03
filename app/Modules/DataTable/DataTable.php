<?php

namespace App\Modules\DataTable;

use Illuminate\Database\Eloquent\Model;
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
	private array $tables_for_columns = [];
	private array $searchable_columns_with_tables = [];
	
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
    
}