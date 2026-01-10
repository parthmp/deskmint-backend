<?php

namespace App\Http\Controllers;


use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\ClientsDetailsFields;
use App\Traits\SettingsDefault;
use Illuminate\Http\Request;

class InvoiceSettingsClientDetailsController extends Controller{

	use SettingsDefault;

	private function getClientDetailFields() : ClientsDetailsFields {
		return new ClientsDetailsFields(ISC_INVOICE_CLIENT_DETAILS_TYPE, 'id', 'clients_custom_field_id');
	}
	
	public function show(Request $request) : mixed{
		
		$company_id = (int) Sanitize::input($request->input('company_id'));
		
		return (new ArrangedFields($this->getClientDetailFields(), $request, $company_id))->fetchArrangedFieldsData(ClientsCustomField::class);

	}

	
	public function saveOrUpdate(Request $request) : mixed{

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new ArrangedFields($this->getClientDetailFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate(ClientsCustomField::class, 'clients');

	}

}
