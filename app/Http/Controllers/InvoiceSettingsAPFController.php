<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\InvoiceSettingsAPF\CreateInvoiceSettingsAPFRequest;
use App\Services\InvoiceSettingsAPF\InvoiceSettingsAPFService;
use Exception;
use Illuminate\Http\Request;

class InvoiceSettingsAPFController extends Controller{

	public function __construct(private InvoiceSettingsAPFService $invoice_settings_apf_service){}

	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{
			return $this->invoice_settings_apf_service->fetch($company_id);
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	/**
	 * regenerateSettings function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return bool
	 */
	private function regenerateSettings(int $company_id) : bool {
		return $this->invoice_settings_apf_service->regenerateSettings($company_id);
	}
	
	
	public function upsert(CreateInvoiceSettingsAPFRequest $request){

		$data = $request->validated();

		if(count($data['labels']) > 3 || count($data['types']) > 3 || count($data['taxes']) > 3){
			return response(['message' => 'Only 3 additional fields are allowed', 'validity' => 'fields_limit_reached'], config('global.error_code'));
		}

		if($this->invoice_settings_apf_service->ifInvalidIdsPresent($data)){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_ids'], config('global.error_code'));
		}

		try{

			$this->invoice_settings_apf_service->update($data);
			$this->regenerateSettings((int) $data['company_id']);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function destroy(Request $request, int $id){

		try{

			$id = (int) Sanitize::input($id);
			$company_id = (int) Sanitize::input($request->input('company_id'));

			$this->invoice_settings_apf_service->destroy($id);

			$this->invoice_settings_apf_service->removeDeletedFromSettingsSection($company_id, $id);

			return response(['message' => 'Removed successfully', 'validity' => 'delete_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
