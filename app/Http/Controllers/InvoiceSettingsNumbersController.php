<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\InvoiceSettingsNumbers\CreateInvoiceSettingsNumbersRequest;
use App\Services\InvoiceSettingsNumbers\InvoiceSettingsNumbersService;
use Exception;
use Illuminate\Http\Request;

class InvoiceSettingsNumbersController extends Controller{

	public function __construct(private InvoiceSettingsNumbersService $invoice_settings_numbers_service){}
	
	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{
			return $this->invoice_settings_numbers_service->fetch($company_id);
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function upsert(CreateInvoiceSettingsNumbersRequest $request){

		$data = $request->validated();

		if(!$this->invoice_settings_numbers_service->ifValidNumberPaddingOption($data['number_padding'])){
			return response(['message' => 'Invalid request','validity' => 'invalid_request_data'], config('global.error_code'));
		}

		if(!$this->invoice_settings_numbers_service->ifValidNumberResetCounterOption($data['reset_counter'])){
			return response(['message' => 'Invalid request','validity' => 'invalid_request_data'], config('global.error_code'));
		}

		try{

			if($this->invoice_settings_numbers_service->updateByObj($data, $this->invoice_settings_numbers_service->fetchRecord($data['company_id']))){
				return response(['message' => 'Settings saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function resetInvoiceNumber(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			if($this->invoice_settings_numbers_service->resetInvoiceNumbers($company_id)){
				return response(['message' => 'Invoice number has been reset successfully', 'validity' => 'reset_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

		
	}

}
