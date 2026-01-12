<?php

namespace App\Http\Controllers;


use App\Http\Requests\GenericRequest;
use App\Models\ClientsCustomField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\ClientsDetailsFields;
use App\Modules\ArrangedFields\Requests\ArrangedFieldsRequest;
use App\Traits\SettingsDefault;

class InvoiceSettingsClientDetailsController extends Controller{

	use SettingsDefault;

	private function getClientDetailFields() : ClientsDetailsFields {
		return new ClientsDetailsFields(ISC_INVOICE_CLIENT_DETAILS_TYPE, 'id', 'clients_custom_field_id');
	}
	
	public function show(GenericRequest $request){
		
		$data = $request->validated();
		
		return (new ArrangedFields($this->getClientDetailFields(), $data))->fetchArrangedFieldsData(ClientsCustomField::class);

	}

	
	public function saveOrUpdate(ArrangedFieldsRequest $request){

		$data = $request->validated();

		$settings_arranged_fields = new ArrangedFields($this->getClientDetailFields(), $data);

		return $settings_arranged_fields->saveOrUpdate(ClientsCustomField::class, 'clients');

	}

}
