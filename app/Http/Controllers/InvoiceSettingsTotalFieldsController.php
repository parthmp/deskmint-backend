<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\TotalFields;
use App\Traits\SettingsDefault;
use Illuminate\Http\Request;

class InvoiceSettingsTotalFieldsController extends Controller{
    
	use SettingsDefault;

	private function getTotalFields() : TotalFields {
		return new TotalFields(ISC_INVOICE_TOTAL_FIELDS_TYPE, '', '');
	}
	
	public function show(Request $request) : mixed{
		
		$company_id = Sanitize::input($request->input('company_id'));
		
		return (new ArrangedFields($this->getTotalFields(), $request, $company_id))->fetchArrangedFieldsData();

	}

	
	public function saveOrUpdate(Request $request) : mixed{

		$company_id = Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new ArrangedFields($this->getTotalFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate('', '');

	}

}
