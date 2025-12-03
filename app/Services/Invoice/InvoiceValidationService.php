<?php

namespace App\Services\Invoice;

use App\Helpers\General;
use App\Models\AdditionalProductColumnsField;
use App\Services\InvoiceSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceValidationService{

	private function validateInvoiceDetails(Request $request) : bool {
		
		$v = Validator::make($request->all(), [
			'data.invoice_details.client.client_id'		=>	'required|exists:clients,id',
			'data.invoice_details.invoice_date.value'	=>	'required',
			'data.invoice_details.invoice_number.value'	=>	'required',
			'data.invoice_details.due_date.value'		=>	'required',
			'data.product_rows'							=>	'required'
		]);

		return (bool) !$v->fails();
	}

	private function validateInvoiceSettings(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'settings.payment_method'				=>	'required',
			'settings.send_invoice_in_email'		=>	'required|boolean',
		]);

		return (bool) !$v->fails();

	}

	private function ifSubmittedFieldsAreSameAsDefined(Request $request, int $company_id) : bool {

		$invoice_settings = new InvoiceSettingsService((int) $company_id);

		$product_columns = $invoice_settings->getProductColumns();

		$product_rows = $request->input('data.product_rows');

		/* now check if all fields exist */

		$fields_same = true;

		$product_row_fields_names = [];
		
		foreach($product_rows[0] as $key => $submitted_col){
			$product_row_fields_names[] = $key;
		}

		$custom_tax_ids = AdditionalProductColumnsField::where([['company_id', '=', $company_id], ['type', '=', 'tax']])->pluck('id')->toArray();

		foreach($product_columns as $user_defined_column){
			
			/* this "normal is for normal fields from DB, not for taxes" */
			if($user_defined_column['mapped'] !== null && $user_defined_column['type'] === 'normal' && !in_array($user_defined_column['mapped'][0], $product_row_fields_names)){
				$fields_same = false;
				break;
			}

			
			if($user_defined_column['mapped'] === null && $user_defined_column['type'] === 'custom'){
				
				if(!isset($user_defined_column['id_column'])){
					$fields_same = false;
					break;
				}

				$with_underscores = General::replaceWithUnderscores($user_defined_column['text']);

				if(in_array($user_defined_column['id_column'], $custom_tax_ids)){
					$custom_field_name = 'custom_tax_'.$with_underscores;
				}else{
					$custom_field_name = 'normal_'.$with_underscores; /* this "normal" indicates non tax custom field */
				}
				
				if(!in_array($custom_field_name, $product_row_fields_names)){
					$fields_same = false;
					break;
				}

			}

			
		}

		return $fields_same;

	}

}