<?php

namespace App\Services\Product;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalProductColumnsField;
use App\Models\AdditionalProductColumnsFieldValue;
use App\Models\Invoice;
use Illuminate\Http\Request;

class ProductFieldService{

	/**
	 * getCustomTaxIds function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	protected function getCustomTaxIds(int $company_id) : array{
		return AdditionalProductColumnsField::where([['company_id', '=', $company_id], ['type', '=', 'tax']])->pluck('id')->toArray();
	}

	/**
	 * isCustomColumn function
	 *
	 * @param array $column
	 * @return boolean
	 */
	protected function isCustomColumn(array $column): bool {
        return ($column['mapped'] === null || $column['mapped'] === '') && $column['type'] === 'custom';
    }

	/**
	 * generateFieldName function
	 *
	 * @param array $column
	 * @param array $custom_tax_ids
	 * @return string
	 */
	protected function generateFieldName(array $column, array $custom_tax_ids): string {

        $underscored = General::replaceWithUnderscores($column['text']);
        return in_array($column['id_column'], $custom_tax_ids) ? 'custom_tax_' . $underscored : 'normal_' . $underscored;

    }

	/**
	 * prepareInsertData function
	 *
	 * @param array $product_rows
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	protected function prepareInsertData(array $product_rows, int $company_id, int $invoice_id): array {

		$invoice = Invoice::where('id', '=', $invoice_id)->first();
		$snapshot = json_decode($invoice->settings_snapshot, true);

		$insert = [];

		$custom_tax_ids = $this->getCustomTaxIds($company_id);

		foreach($snapshot as $user_defined_column){
			
			if($this->isCustomColumn($user_defined_column)){
				
				$temp = [];

				$custom_field_name = $this->generateFieldName($user_defined_column, $custom_tax_ids);

				foreach($product_rows as $row){
					$temp['row_uuid'] = Sanitize::input($row['row_uuid']);
					$temp['invoice_id'] = (int) $invoice_id;
					$temp['apc_field_id'] = $user_defined_column['id_column'];
					$value = $row[$custom_field_name] ?? '';
					$temp['value'] = Sanitize::input($value);
				}

				$insert[] = $temp;

			}
			
			
		}

		return $insert;

	}

	/**
	 * insertProductRows function
	 *
	 * @param Request $request
	 * @param integer $invoice_id
	 * @param integer $company_id
	 * @return void
	 */
	public function insertProductRows(Request $request, int $invoice_id, int $company_id) : void {

		$product_rows_path = 'data.product_rows';

		$product_rows = $request->input($product_rows_path);
		$rows = $this->prepareInsertData($product_rows, $company_id, $invoice_id);
		AdditionalProductColumnsFieldValue::insert($rows);

	}

}