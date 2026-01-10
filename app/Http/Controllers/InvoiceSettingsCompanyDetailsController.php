<?php

namespace App\Http\Controllers;


use App\Http\Requests\GenericRequest;
use App\Models\AdditionalCompanyField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\CompanyDetailsFields;
use App\Modules\ArrangedFields\Requests\ArrangedFieldsRequest;


class InvoiceSettingsCompanyDetailsController extends Controller{

	private function getCompanyDetailFields() : CompanyDetailsFields {
		return new CompanyDetailsFields(ISC_INVOICE_COMPANY_DETAILS_TYPE, 'id', 'id_column');
	}

	public function show(GenericRequest $request) : mixed {

		$data = $request->validated();

		return (new ArrangedFields($this->getCompanyDetailFields(), $data))->fetchArrangedFieldsData();
		
	}

	
	public function saveOrUpdate(ArrangedFieldsRequest $request) : mixed {

		$data = $request->validated();

		$settings_arranged_fields = new ArrangedFields($this->getCompanyDetailFields(), $data);

		return $settings_arranged_fields->saveOrUpdate(AdditionalCompanyField::class, 'companies');

	}

}
