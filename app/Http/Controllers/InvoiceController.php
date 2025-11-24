<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalProductColumnsField;
use App\Models\Client;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Services\HandleInvoiceNumbers;
use App\Services\InvoiceSettingsService;
use App\Traits\CustomFieldsPrinting;
use App\Traits\CustomFieldsValidation;
use App\Traits\PaymentGatewayDetails;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller{

	use CustomFieldsPrinting, PaymentGatewayDetails, CustomFieldsValidation;
    
	/**
	 * searchClients function
	 *
	 * @param Request $request
	 * @return Collection
	 */
	public function searchClients(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));
		$searched = Sanitize::input($request->input('searched'));

		$clients = Client::select('id', 'first_name', 'last_name', 'currency_id')->where('company_id', '=', $company_id)->where(function($query) use($searched){
			$query->where('first_name', 'LIKE', '%'.$searched.'%');
			$query->orwhere('last_name', 'LIKE', '%'.$searched.'%');
		})->with('currency')->orderBy('first_name', 'ASC')->limit(50)->get()->map(function($client){
			return [
				'text'		=>	$client->first_name.' '.$client->last_name,
				'value'		=>	$client->id,
				'data'		=>	[
					'currency'	=>	$client->currency
				]
			];
		})->toArray();

		return $clients;

	}

	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchInitialData(Request $request){

		$v = Validator::make($request->all(), [
			'timezone_offset_minutes'	=>	'required'
		]);

		if($v->fails()){
			return response(['message' => 'Invalid request', 'validator' => 'invalid_timezone'], config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));
		$timezone_offset_minutes = Sanitize::input($request->input('timezone_offset_minutes'));

		try{
			
			$invoice_settings = new InvoiceSettingsService((int) $company_id);

			$custom_fields = $this->fetchInvoiceCustomFields($request);

			/* get payment integration data */
			$gateways = $this->getGateWayNames((int) $company_id);


			return [
				'invoice_number'	=>	(new HandleInvoiceNumbers((int) $company_id, $invoice_settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->getNextInvoiceNumber(),
				'product_columns' 	=> 	$invoice_settings->getProductColumns(),
				'total_fields' 		=> 	$invoice_settings->getTotalFields(),
				'custom_fields'		=>	$custom_fields['data_fields'],
				'gateways'			=>	$gateways
			];

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function fetchProducts(Request $request){
		
		$company_id = Sanitize::input($request->input('company_id'));
		$searched = Sanitize::input($request->input('searched'));

		$products = Product::select('id', 'product_name', 'description', 'price')->where('company_id', '=', $company_id)->where(function($query) use($searched){
			$query->where('product_name', 'LIKE', '%'.$searched.'%');
		})->orderBy('product_name', 'ASC')->limit(50)->get()->map(function($product){
			return [
				'text'		=>	$product->product_name,
				'value'		=>	$product->id,
				'data'		=>	[
					'product' => $product
				]
			];
		})->toArray();

		return $products;


	}

	/**
	 * fetchInvoiceCustomFields function
	 *
	 * @param Request $request
	 * @return void
	 */
	private function fetchInvoiceCustomFields(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = InvoicesCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();

		return 	[
					'data_fields' 	=> $this->adjustRowsPrinting($fields),
				];
	}

	
	/**
	 * validateInvoiceDetails function
	 *
	 * @param Request $request
	 * @return boolean
	 */
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

	/**
	 * validateSettings function
	 *
	 * @param Request $request
	 * @return boolean
	 */
	private function validateSettings(Request $request) : bool {

		$v = Validator::make($request->all(), [
			'settings.payment_method'				=>	'required',
			'settings.send_invoice_in_email'		=>	'required|boolean',
		]);

		return (bool) !$v->fails();

	}

	/**
	 * ifSubmittedFieldsAreSameAsDefined function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return mixed
	 */
	private function ifSubmittedFieldsAreSameAsDefined(Request $request, int $company_id) : mixed {

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

	/**
	 * store function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function store(Request $request){

		// $tab0_valid = $this->validateInvoiceDetails($request);
		
		// if(!$tab0_valid){
		// 	return response(['message' => 'Please fill in required fields', 'validity' => 'invalid_request', 'tab_switch' => 0], config('global.error_code'));
		// }


		// $tab1_valid = $this->validateCustomFields($request, InvoicesCustomField::class, 'invalid_data_tab1', 1);
		// if($tab1_valid !== null){
		// 	return $tab1_valid;
		// }

		// $tab2_valid = $this->validateSettings($request);

		// if(!$tab2_valid){
		// 	return response(['message' => 'Please fill in required fields', 'validity' => 'invalid_request', 'tab_switch' => 2], config('global.error_code'));
		// }

		$discount_number = 0;

		if($request->filled('data.invoice_details.global_discount')){
			$discount_number = (float) Sanitize::input($request->input('data.invoice_details.global_discount'));
		}

		$discount_type = 'percentage';

		if($request->filled('data.invoice_details.global_discount_type')){
			$discount_type = Sanitize::input($request->input('data.invoice_details.global_discount_type'));
			if($discount_type !== 'percentage'){
				$discount_type = 'amount';
			}
		}

		$company_id = (int) Sanitize::input($request->input('company_id'));

		
		if(!$request->filled('data.product_rows')){
			return response(['message' => 'Please have at least product to create invoice', 'validity' => 'invalid_request', 'tab_switch' => 0], config('global.error_code'));
		}

		$product_rows = $request->input('data.product_rows');
		if(count($product_rows) === 0){
			return response(['message' => 'Please have at least product to create invoice', 'validity' => 'invalid_request', 'tab_switch' => 0], config('global.error_code'));
		}
		
		if(!$this->ifSubmittedFieldsAreSameAsDefined($request, $company_id)){
			return response(['message' => 'Invalid request, fields do not match', 'validity' => 'mismatch_fields', 'tab_switch' => 2], config('global.error_code'));
		}


		$rows = [];

		foreach($product_rows as $product_row){

			$line_tax_amount = 0;
			$line_subtotal = 0;
			$line_total = 0;
			
			
		}


		/* now calculate per line */



		/**
		 * check for discount field
		 * check for discount type field
		 * fetch the defined fields from db
		 * compare if frontend sent same fields.
		 * 
		 */

	}

}
