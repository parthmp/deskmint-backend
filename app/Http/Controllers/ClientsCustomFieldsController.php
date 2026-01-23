<?php

namespace App\Http\Controllers;


use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Models\ClientsCustomField;
use App\Models\CustomFieldType;
use App\Modules\CustomFieldsFeature\CustomFieldsFeature;
use App\Modules\CustomFieldsFeature\Requests\CreateCustomFieldsFeatureRequest;
use App\Modules\CustomFieldsFeature\Requests\DeleteCustomFieldsFeatureRequest;
use App\Modules\DataTable\Requests\DataTableRequest;

class ClientsCustomFieldsController extends Controller{
	
	public function __construct(private CustomFieldsFeature $custom_fields_feature){}

	private string $custom_id_flag = 'clients_custom_field_id';
	private string $model = ClientsCustomField::class;

	public function fetchFieldTypes(GenericRequest $request){
		return $this->custom_fields_feature->setModel(CustomFieldType::class)->fetchFieldTypes();
	}

	public function store(CreateCustomFieldsFeatureRequest $request){
		/**
		 * TODO : use try and catch for error handling here
		 */
		return $this->custom_fields_feature->setModel($this->model)->saveOrUpdateCustomField($request->validated(), 'client', true, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function index(DataTableRequest $request){
		/**
		 * TODO : use try and catch for error handling here
		 */
		return $this->custom_fields_feature->setModel($this->model)->indexData($request->validated(), 'client');
	}

	public function show(GenericRequest $request, int $id){
		/**
		 * TODO : use try and catch for error handling here
		 */
		$data = $request->validated();
		$company_id = $data['company_id'];
		$id = (int) Sanitize::input($id);
		return $this->custom_fields_feature->setModel($this->model)->showData($company_id, $id);
	}

	public function update(CreateCustomFieldsFeatureRequest $request, int $id){
		/**
		 * TODO : use try and catch for error handling here
		 */
		return $this->custom_fields_feature->setModel($this->model)->updateData($request->validated(), 'client', $id, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function destroy(DeleteCustomFieldsFeatureRequest $request){
		/**
		 * TODO : use try and catch for error handling here
		 */
		$data = $request->validated();
		$company_id = $data['company_id'];
		return $this->custom_fields_feature->setModel($this->model)->destroyData($request->validated(), 'client', ISC_INVOICE_CLIENT_DETAILS_TYPE, $company_id, $this->custom_id_flag);
	}
}
