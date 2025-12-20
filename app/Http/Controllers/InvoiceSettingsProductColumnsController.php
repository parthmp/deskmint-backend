<?php

namespace App\Http\Controllers;

use App\FieldDefinitions\ArrangedFields\ProductColumnsFields;
use App\FieldDefinitions\ArrangedFields\SettingsArrangedFields;
use App\Helpers\Sanitize;
use App\Models\AdditionalProductColumnsField;
use App\Traits\SettingsDefault;
use Illuminate\Http\Request;

class InvoiceSettingsProductColumnsController extends Controller{
	
    use SettingsDefault;

	private function getProductColumnFields() : ProductColumnsFields {
		return new ProductColumnsFields(ISC_PRODUCT_COLUMNS_TYPE, 'id', 'id_column');
	}
	
	public function show(Request $request) : mixed{
		
		$company_id = (int) Sanitize::input($request->input('company_id'));
		
		return (new SettingsArrangedFields($this->getProductColumnFields(), $request, $company_id))->fetchArrangedFieldsData();

	}

	
	public function saveOrUpdate(Request $request) : mixed{

		$company_id = (int) Sanitize::input($request->input('company_id'));

		$settings_arranged_fields = new SettingsArrangedFields($this->getProductColumnFields(), $request, $company_id);

		return $settings_arranged_fields->saveOrUpdate(AdditionalProductColumnsField::class, 'invoice_items',  ['product_id' => 'Item']);

	}
}
