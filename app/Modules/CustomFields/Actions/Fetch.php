<?php

namespace App\Modules\CustomFields\Actions;

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
		return $this->model::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();
	}

}