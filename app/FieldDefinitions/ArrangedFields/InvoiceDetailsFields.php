<?php

namespace App\FieldDefinitions\ArrangedFields;

use App\Interfaces\ArrangedFieldsInterface;
use App\Traits\SettingsDefault;

class InvoiceDetailsFields implements ArrangedFieldsInterface{

	use SettingsDefault;

	private string $type = '';
	private string $id_column = '';
	private string $json_prop = '';

	public function __construct(string $type, string $id_column, string $json_prop){
		$this->type = $type;
		$this->id_column = $id_column;
		$this->json_prop = $json_prop;
	}

	public function fetchDefaultArrangedFieldsData(int $company_id){
		return $this->getDefaultInvoiceDetailsSettings($company_id);
	}

	public function getType(): string {
		return $this->type;
	}

	public function getIdColumn(): string {
		return $this->id_column;
	}

	public function getJsonColumn(): string {
		return $this->json_prop;
	}

}