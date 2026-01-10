<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Models\AdditionalCompanyField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\CompanyAddressFields;
use App\Modules\ArrangedFields\Requests\ArrangedFieldsRequest;


class InvoiceSettingsCompanyAddressController extends Controller{

    private function getCompanyAddressFields() : CompanyAddressFields {
		return new CompanyAddressFields(ISC_INVOICE_COMPANY_ADDRESS_TYPE, 'id', 'id_column');
	}

	public function show(GenericRequest $request) : mixed{

		$data = $request->validated();

		return (new ArrangedFields($this->getCompanyAddressFields(), $data))->fetchArrangedFieldsData();
		
	}

	
	public function saveOrUpdate(ArrangedFieldsRequest $request) : mixed{

		$data = $request->validated();

		$settings_arranged_fields = new ArrangedFields($this->getCompanyAddressFields(), $data);

		return $settings_arranged_fields->saveOrUpdate(AdditionalCompanyField::class, 'companies');

	}
}
