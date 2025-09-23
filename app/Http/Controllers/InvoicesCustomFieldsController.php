<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\CustomFieldType;
use App\Models\InvoicesCustomField;
use App\Traits\FeatureCustomFields;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoicesCustomFieldsController extends Controller{

	use FeatureCustomFields;

	public function fetchFieldTypes(Request $request){
		return $this->fetchFieldTypesData(CustomFieldType::class);
	}

	public function store(Request $request){
		return $this->saveOrUpdateCustomField($request, InvoicesCustomField::class, 'invoice', true);
	}

	public function index(Request $request){
		return $this->indexData($request, InvoicesCustomField::class, 'invoice');
	}

	public function show(Request $request, int $id){
		$company_id = Sanitize::input($request->input('company_id'));
		return $this->showData(InvoicesCustomField::class, $company_id, $id);
	}

	public function update(Request $request, int $id){
		return $this->updateData($request, InvoicesCustomField::class, 'invoice', $id);
	}

	public function destroy(Request $request): Response{
		return $this->destroyData($request, InvoicesCustomField::class, 'invoice');
	}

}
