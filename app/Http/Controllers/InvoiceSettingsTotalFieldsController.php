<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\TotalFields;
use App\Modules\ArrangedFields\Requests\ArrangedFieldsRequest;
use App\Traits\SettingsDefault;

class InvoiceSettingsTotalFieldsController extends Controller{
    
	use SettingsDefault;

	private function getTotalFields() : TotalFields {
		return new TotalFields(ISC_INVOICE_TOTAL_FIELDS_TYPE, '', '');
	}
	
	public function show(GenericRequest $request){
		
		$data = $request->validated();
		
		return (new ArrangedFields($this->getTotalFields(), $data))->fetchArrangedFieldsData();

	}

	
	public function saveOrUpdate(ArrangedFieldsRequest $request){

		$data = $request->validated();

		$settings_arranged_fields = new ArrangedFields($this->getTotalFields(), $data);

		return $settings_arranged_fields->saveOrUpdate('', '');

	}

}
