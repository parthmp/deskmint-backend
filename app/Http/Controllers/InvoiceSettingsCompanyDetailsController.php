<?php

namespace App\Http\Controllers;

use App\FieldDefinitions\ArrangedFields\CompanyDetailsFields;
use App\FieldDefinitions\ArrangedFields\SettingsArrangedFields;
use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use Illuminate\Http\Request;


class InvoiceSettingsCompanyDetailsController extends Controller{

	private function getCompanyDetailFields() : CompanyDetailsFields {
		return new CompanyDetailsFields(ISC_INVOICE_COMPANY_DETAILS_TYPE, 'id', 'id_column');
	}

	public function show(Request $request) : mixed {

		$company_id = (int) Sanitize::input($request->input('company_id'));

		return (new SettingsArrangedFields($this->getCompanyDetailFields(), $request, $company_id))->fetchArrangedFieldsData();
		
	}

	
	public function saveOrUpdate(Request $request) : mixed {

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new SettingsArrangedFields($this->getCompanyDetailFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate(AdditionalCompanyField::class, 'companies');

	}

}
