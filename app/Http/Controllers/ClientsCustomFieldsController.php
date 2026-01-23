<?php

namespace App\Http\Controllers;


use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Models\ClientsCustomField;
use App\Modules\CustomFieldsFeature\CustomFieldsFeature;
use App\Modules\CustomFieldsFeature\Requests\CreateCustomFieldsFeatureRequest;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Traits\FeatureCustomFields;
use Illuminate\Http\Response;

class ClientsCustomFieldsController extends Controller{
	
	use FeatureCustomFields;

	public function __construct(private CustomFieldsFeature $custom_fields_feature){}

	private string $custom_id_flag = 'clients_custom_field_id';

	public function fetchFieldTypes(GenericRequest $request){
		return $this->custom_fields_feature->fetchFieldTypes();
		//return $this->fetchFieldTypesData(CustomFieldType::class);
	}

	public function store(CreateCustomFieldsFeatureRequest $request){
		//return $this->saveOrUpdateCustomField($request, ClientsCustomField::class, 'client', true, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
		return $this->custom_fields_feature->saveOrUpdateCustomField($request->validated(), ClientsCustomField::class, 'client', true, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function index(DataTableRequest $request){
		//return $this->indexData($request, ClientsCustomField::class, 'client');
		return $this->custom_fields_feature->indexData($request->validated(), ClientsCustomField::class, 'client');
	}

	public function show(GenericRequest $request, int $id){
		$data = $request->validated();
		$company_id = $data['company_id'];
		$id = (int) Sanitize::input($id);
		//return $this->showData(ClientsCustomField::class, $company_id, $id);
		return $this->custom_fields_feature->showData(ClientsCustomField::class, $company_id, $id);
	}

	public function update(CreateCustomFieldsFeatureRequest $request, int $id){
		return $this->updateData($request, ClientsCustomField::class, 'client', $id, ISC_INVOICE_CLIENT_DETAILS_TYPE, $this->custom_id_flag);
	}

	public function destroy(GenericRequest $request): Response{
		$company_id = (int) Sanitize::input($request->input('company_id'));
		return $this->destroyData($request, ClientsCustomField::class, 'client', ISC_INVOICE_CLIENT_DETAILS_TYPE, $company_id, $this->custom_id_flag);
	}
}
