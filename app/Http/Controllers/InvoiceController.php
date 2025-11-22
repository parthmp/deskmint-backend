<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Client;
use App\Models\InvoicesCustomField;
use App\Models\Product;
use App\Models\SettingsSection;
use App\Services\HandleInvoiceNumbers;
use App\Services\InvoiceSettingsService;
use App\Traits\CustomFieldsPrinting;
use App\Traits\PaymentGatewayDetails;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class InvoiceController extends Controller{

	use CustomFieldsPrinting, PaymentGatewayDetails;
    
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
	 * store function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function store(Request $request){

		$v = Validator::make($request->all(), [
			'invoice_details.client.client_id'		=>	'required|exists:clients,id',
			'invoice_details.invoice_date.value'	=>	'required',
			'invoice_details.invoice_number.value'	=>	'required',
			'invoice_details.due_date.value'		=>	'required',
		]);
		
		if($v->fails()){
			return response(['message' => 'Please fill in the required fields', 'validity' => 'invalid_request', 'tab_switch' => 0], config('global.error_code'));
		}

		

	}

}
