<?php

namespace App\Http\Controllers;


use App\Http\Requests\GenericRequest;
use App\Models\AdditionalProductColumnsField;
use App\Modules\ArrangedFields\ArrangedFields;
use App\Modules\ArrangedFields\Implementation\ProductColumnsFields;
use App\Modules\ArrangedFields\Requests\ArrangedFieldsRequest;
use App\Traits\SettingsDefault;

class InvoiceSettingsProductColumnsController extends Controller{
	
    use SettingsDefault;

	private function getProductColumnFields() : ProductColumnsFields {
		return new ProductColumnsFields(ISC_PRODUCT_COLUMNS_TYPE, 'id', 'id_column');
	}
	
	public function show(GenericRequest $request) : mixed{
		
		$data = $request->validated();
		
		return (new ArrangedFields($this->getProductColumnFields(), $data))->fetchArrangedFieldsData();

	}

	
	public function saveOrUpdate(ArrangedFieldsRequest $request) : mixed{

		$data = $request->validated();

		$settings_arranged_fields = new ArrangedFields($this->getProductColumnFields(), $data);

		return $settings_arranged_fields->saveOrUpdate(AdditionalProductColumnsField::class, 'invoice_items',  ['product_id' => 'Item']);

	}
}
