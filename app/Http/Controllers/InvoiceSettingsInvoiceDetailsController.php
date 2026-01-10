<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\InvoicesCustomField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\InvoiceDetailsFields;
use App\Traits\SettingsDefault;
use Illuminate\Http\Request;

class InvoiceSettingsInvoiceDetailsController extends Controller{
    
	use SettingsDefault;

	private function getInvoiceDetailFields() : InvoiceDetailsFields {
		return new InvoiceDetailsFields(ISC_INVOICE_DETAILS_TYPE, 'id', 'invoices_custom_field_id');
	}
	
	public function show(Request $request) : mixed{
		
		$company_id = (int) Sanitize::input($request->input('company_id'));
		
		return (new ArrangedFields($this->getInvoiceDetailFields(), $request, $company_id))->fetchArrangedFieldsData(InvoicesCustomField::class);

	}

	
	public function saveOrUpdate(Request $request) : mixed{

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new ArrangedFields($this->getInvoiceDetailFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate(InvoicesCustomField::class, 'invoices');

	}

}
