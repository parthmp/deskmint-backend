<?php

namespace App\Modules\ArrangedFields\Implementation;

use App\Modules\ArrangedFields\Contracts\ArrangedFieldsInterface;
use App\Traits\SettingsDefault;

class TotalFields implements ArrangedFieldsInterface{

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
		return $this->getDefaultTotalFieldsSettings();
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