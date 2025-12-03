<?php

namespace App\Http\Controllers;

use App\DiClasses\ArrangedFields\ClientsDetailsFields;
use App\DiClasses\ArrangedFields\SettingsArrangedFields;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsClientDetailsController extends Controller{

	use SettingsDefault;

	private function getClientDetailFields() : ClientsDetailsFields {
		return new ClientsDetailsFields(ISC_INVOICE_CLIENT_DETAILS_TYPE, 'id', 'clients_custom_field_id');
	}
	
	public function show(Request $request) : mixed{
		
		$company_id = (int) Sanitize::input($request->input('company_id'));
		
		return (new SettingsArrangedFields($this->getClientDetailFields(), $request, $company_id))->fetchArrangedFieldsData(ClientsCustomField::class);

	}

	
	public function saveOrUpdate(Request $request) : mixed{

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new SettingsArrangedFields($this->getClientDetailFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate(ClientsCustomField::class, 'clients');

	}

}
