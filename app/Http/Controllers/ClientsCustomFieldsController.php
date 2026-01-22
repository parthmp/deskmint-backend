<?php

namespace App\Http\Controllers;


use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Models\CustomFieldType;
use App\Modules\CustomFieldsFeature\CustomFieldsFeature;
use App\Modules\CustomFieldsFeature\Requests\CreateCustomFieldsFeatureRequest;
use App\Traits\FeatureCustomFields;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClientsCustomFieldsController extends Controller{
	
	use FeatureCustomFields;

	public function __construct(private CustomFieldsFeature $custom_fields_feature){}

	private string $custom_id_flag = 'clients_custom_field_id';

	public function fetchFieldTypes(Request $request){
		return $this->custom_fields_feature->fetchFieldTypes();
		//return $this->fetchFieldTypesData(CustomFieldType::class);
	}

	public function store(CreateCustomFieldsFeatureRequest $request){
		//return $this->saveOrUpdateCustomField($request, ClientsCustomField::class, 'client', true, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
		return $this->custom_fields_feature->saveOrUpdateCustomField($request->validated(), ClientsCustomField::class, 'client', true, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function index(Request $request){
		return $this->indexData($request, ClientsCustomField::class, 'client');
	}

	public function show(Request $request, int $id){
		$company_id = (int) Sanitize::input($request->input('company_id'));
		return $this->showData(ClientsCustomField::class, $company_id, $id);
	}

	public function update(Request $request, int $id){
		return $this->updateData($request, ClientsCustomField::class, 'client', $id, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function destroy(Request $request): Response{
		$company_id = (int) Sanitize::input($request->input('company_id'));
		return $this->destroyData($request, ClientsCustomField::class, 'client', ISC_INVOICE_CLIENT_DETAILS_TYPE, $company_id, $this->custom_id_flag);
	}
}
