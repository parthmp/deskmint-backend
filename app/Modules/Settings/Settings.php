<?php

namespace App\Modules\Settings;

use App\Traits\SettingsDefault;

class Settings {

	use SettingsDefault;

	private int $company_id;
	private array $all_settings = [];

	public function __construct(
		private DB $db
	){}

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
	 * fetch function
	 *
	 * @return self
	 */
	public function fetch() : self {
		$this->all_settings = $this->db->fetchAllSettings($this->company_id);
		return $this;
	}

	/**
	 * filter function
	 *
	 * @param string $type
	 * @return array|null
	 */
	private function filter(string $type) : ?array {

		foreach($this->all_settings as $setting){
			if((string) $setting['type'] === $type){

				return json_decode($setting['settings_json'], true);

			}
		}

		return null;

	}

	/**
	 * InvoiceRowSettings function
	 *
	 * @return array
	 */
	public function InvoiceRowSettings() : array {

		$data = $this->filter(ISC_PRODUCT_COLUMNS_TYPE);
		if(!$data){
			return $this->getDefaultProductColumnsSettings($this->company_id);
		}

		return $data;

	}

}