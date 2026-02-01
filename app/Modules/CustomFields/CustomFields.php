<?php

namespace App\Modules\CustomFields;

use App\Modules\CustomFields\Actions\Fetch;
use App\Modules\CustomFields\Actions\Printing;
use App\Modules\CustomFields\Actions\Upsert;
use App\Modules\CustomFields\Actions\Validation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CustomFields{

	public function __construct(private Printing $printing, private Validation $validation, private Upsert $upsert, private Fetch $fetch){}

	/**
	 * printCustomFields function
	 *
	 * @param Collection $fields
	 * @return \Illuminate\Support\Collection
	 */
	public function printCustomFields(Collection $fields) : \Illuminate\Support\Collection {
		return $this->printing->adjustRowsPrinting($fields);
	}

	/**
	 * validateCustomFields function
	 *
	 * @param Request $request
	 * @param string $model
	 * @param string $validity
	 * @param integer $tab
	 * @return boolean
	 */
	public function validateCustomFields(Request $request, string $model, string $validity, int $tab = 3) : bool {
		return $this->validation->validateCustomFields($request, $model, $validity, $tab);
	}

	/**
	 * upsertCustomFieldValues function
	 *
	 * @param Request $request
	 * @param integer $db_id
	 * @param string $custom_fields_model
	 * @param string $custom_fields_value_model
	 * @param string $flat_table
	 * @param string $type
	 * @param boolean $add
	 * @return void
	 */
	public function upsertCustomFieldValues(Request $request, int $db_id, string $custom_fields_model, string $custom_fields_value_model, string $flat_table, string $type = 'client', bool $add = true) : void {
		$this->upsert->upsertCustomFieldValues($request, $db_id, $custom_fields_model, $custom_fields_value_model, $flat_table, $type, $add);
	}

	/**
	 * fetchCustomFields function
	 *
	 * @param string $model
	 * @param integer $company_id
	 * @return Collection
	 */
	public function fetchCustomFields(string $model, int $company_id) : Collection {
		return $this->fetch->setModel($model)->fetchCustomFields($company_id);
	}

}