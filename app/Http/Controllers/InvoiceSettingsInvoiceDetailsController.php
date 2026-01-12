<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenericRequest;
use App\Models\InvoicesCustomField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\InvoiceDetailsFields;
use App\Modules\ArrangedFields\Requests\ArrangedFieldsRequest;
use App\Traits\SettingsDefault;

class InvoiceSettingsInvoiceDetailsController extends Controller{
    
	use SettingsDefault;

	private function getInvoiceDetailFields() : InvoiceDetailsFields {
		return new InvoiceDetailsFields(ISC_INVOICE_DETAILS_TYPE, 'id', 'invoices_custom_field_id');
	}
	
	public function show(GenericRequest $request){
		
		$data = $request->validated();
		
		return (new ArrangedFields($this->getInvoiceDetailFields(), $data))->fetchArrangedFieldsData(InvoicesCustomField::class);

	}

	
	public function saveOrUpdate(ArrangedFieldsRequest $request){

		$data = $request->validated();

		$settings_arranged_fields = new ArrangedFields($this->getInvoiceDetailFields(), $data);

		return $settings_arranged_fields->saveOrUpdate(InvoicesCustomField::class, 'invoices');

	}

}
