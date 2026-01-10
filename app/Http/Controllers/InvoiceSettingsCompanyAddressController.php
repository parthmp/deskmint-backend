<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\CompanyAddressFields;
use Illuminate\Http\Request;

class InvoiceSettingsCompanyAddressController extends Controller{

    private function getCompanyAddressFields() : CompanyAddressFields {
		return new CompanyAddressFields(ISC_INVOICE_COMPANY_ADDRESS_TYPE, 'id', 'id_column');
	}

	public function show(Request $request) : mixed{

		$company_id = (int) Sanitize::input($request->input('company_id'));

		return (new ArrangedFields($this->getCompanyAddressFields(), $request, $company_id))->fetchArrangedFieldsData();
		
	}

	
	public function saveOrUpdate(Request $request) : mixed{

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new ArrangedFields($this->getCompanyAddressFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate(AdditionalCompanyField::class, 'companies');

	}
}
