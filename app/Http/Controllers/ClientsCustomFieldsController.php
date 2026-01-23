<?php

namespace App\Http\Controllers;


use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Models\ClientsCustomField;
use App\Modules\CustomFieldsFeature\CustomFieldsFeature;
use App\Modules\CustomFieldsFeature\Requests\CreateCustomFieldsFeatureRequest;
use App\Modules\CustomFieldsFeature\Requests\DeleteCustomFieldsFeatureRequest;
use App\Modules\DataTable\Requests\DataTableRequest;

class ClientsCustomFieldsController extends Controller{
	
	public function __construct(private CustomFieldsFeature $custom_fields_feature){}

	private string $custom_id_flag = 'clients_custom_field_id';

	public function fetchFieldTypes(GenericRequest $request){
		return $this->custom_fields_feature->fetchFieldTypes();
	}

	public function store(CreateCustomFieldsFeatureRequest $request){
		/**
		 * TODO : use try and catch for error handling here
		 */
		return $this->custom_fields_feature->saveOrUpdateCustomField($request->validated(), ClientsCustomField::class, 'client', true, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function index(DataTableRequest $request){
		/**
		 * TODO : use try and catch for error handling here
		 */
		return $this->custom_fields_feature->indexData($request->validated(), ClientsCustomField::class, 'client');
	}

	public function show(GenericRequest $request, int $id){
		/**
		 * TODO : use try and catch for error handling here
		 */
		$data = $request->validated();
		$company_id = $data['company_id'];
		$id = (int) Sanitize::input($id);
		return $this->custom_fields_feature->showData(ClientsCustomField::class, $company_id, $id);
	}

	public function update(CreateCustomFieldsFeatureRequest $request, int $id){
		/**
		 * TODO : use try and catch for error handling here
		 */
		return $this->custom_fields_feature->updateData($request->validated(), ClientsCustomField::class, 'client', $id, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function destroy(DeleteCustomFieldsFeatureRequest $request){
		/**
		 * TODO : use try and catch for error handling here
		 */
		$data = $request->validated();
		$company_id = $data['company_id'];
		return $this->custom_fields_feature->destroyData($request->validated(), ClientsCustomField::class, 'client', ISC_INVOICE_CLIENT_DETAILS_TYPE, $company_id, $this->custom_id_flag);
	}
}
