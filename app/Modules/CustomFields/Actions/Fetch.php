<?php

namespace App\Modules\CustomFields\Actions;

use App\Helpers\General;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class Fetch{

	private string $model;

	/**
	 * setModel function
	 *
	 * @param string $model
	 * @return self
	 */
	public function setModel(string $model) : self {
		$this->model = $model;
		return $this;
	}

	/**
	 * fetchCustomFields function
	 *
	 * @param integer $company_id
	 * @return Collection
	 */
	public function fetchCustomFields(int $company_id) : Collection {
		
		$collection = $this->model::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();
		return $collection;
		
	}


	/**
	 * fetchCustomFieldsArray function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchCustomFieldsArray(int $company_id) : array {
		return $this->model::where('company_id', '=', $company_id)->whereHas('customFieldType')->with('customFieldType')->get()->toArray();
	}

	/**
	 * fetchCustomFieldValues function
	 *
	 * @param integer $id
	 * @param string $type
	 * @return Collection|null
	 */
	public function fetchCustomFieldValues(int $id, string $type) : ?Collection {
		$first_upper = ucfirst($type);
		return $this->model::where($type.'_id', '=', $id)->whereHas($first_upper.'sCustomField')->whereHas($first_upper.'sCustomField.customFieldType')->with($first_upper.'sCustomField', $first_upper.'sCustomField.customFieldType')->get();
	}

}