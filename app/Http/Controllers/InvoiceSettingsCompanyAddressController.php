<?php

namespace App\Http\Controllers;

use App\DiClasses\ArrangedFields\CompanyAddressFields;
use App\DiClasses\ArrangedFields\SettingsArrangedFields;
use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use Illuminate\Http\Request;

class InvoiceSettingsCompanyAddressController extends Controller{

    private function getCompanyAddressFields() : CompanyAddressFields {
		return new CompanyAddressFields(ISC_INVOICE_COMPANY_ADDRESS_TYPE, 'id', 'id_column');
	}

	public function show(Request $request) : mixed{

		$company_id = Sanitize::input($request->input('company_id'));

		return (new SettingsArrangedFields($this->getCompanyAddressFields(), $request, $company_id))->fetchArrangedFieldsData();
		
	}

	
	public function saveOrUpdate(Request $request) : mixed{

		$company_id = Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new SettingsArrangedFields($this->getCompanyAddressFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate(AdditionalCompanyField::class, 'companies');

	}
}
