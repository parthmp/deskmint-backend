<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\CustomFieldType;
use App\Models\InvoicesCustomField;
use App\Traits\FeatureCustomFields;
use Illuminate\Http\Request;

class InvoicesCustomFieldsController extends Controller{

	use FeatureCustomFields;

	public function fetchFieldTypes(Request $request){
		
		return $this->fetchFieldTypesData(CustomFieldType::class);

	}

	public function store(Request $request){
		
		$company_id = Sanitize::input($request->input('company_id'));
		return $this->saveOrUpdateCustomField($request, InvoicesCustomField::class, $company_id, 'invoice', true);
	
	}

}
