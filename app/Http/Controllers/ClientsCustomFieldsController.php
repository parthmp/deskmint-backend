<?php

namespace App\Http\Controllers;


use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Models\CustomFieldType;
use App\Traits\FeatureCustomFields;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClientsCustomFieldsController extends Controller{
	
	use FeatureCustomFields;

	public function fetchFieldTypes(Request $request){
		return $this->fetchFieldTypesData(CustomFieldType::class);
	}

	public function store(Request $request){
		return $this->saveOrUpdateCustomField($request, ClientsCustomField::class, 'client', true);
	}

	public function index(Request $request){
		return $this->indexData($request, ClientsCustomField::class, 'client');
	}

	public function show(Request $request, int $id){
		$company_id = Sanitize::input($request->input('company_id'));
		return $this->showData(ClientsCustomField::class, $company_id, $id);
	}

	public function update(Request $request, int $id){
		return $this->updateData($request, ClientsCustomField::class, 'client', $id);
	}

	public function destroy(Request $request): Response{
		return $this->destroyData($request, ClientsCustomField::class, 'client');
	}
}
