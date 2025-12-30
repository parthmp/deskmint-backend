<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\InvoiceSettingsGeneral\CreateInvoiceSettingsGeneralRequest;
use App\Services\InvoiceSettingsGeneral\InvoiceSettingsGeneralService;
use Exception;
use Illuminate\Http\Request;

class InvoiceSettingsGeneralController extends Controller{

	public function __construct(private InvoiceSettingsGeneralService $invoice_settings_general_service){}

	/**
	 * fetchTemplates function
	 *
	 * @return array
	 */
	private function fetchTemplates() : array{

		return $this->invoice_settings_general_service->fetchTemplates();

	}

	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			$arr = $this->invoice_settings_general_service->fetch($company_id);

			if($arr['settings']){
				return 	[
						'settings' 	=> 	json_decode($arr['settings']->settings_json, true),
						'templates'	=>	$this->fetchTemplates()
					];
			}

			return 	[
						'settings' 	=> 	$arr['default'],
						'templates'	=>	$this->fetchTemplates()
					];

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function upsert(CreateInvoiceSettingsGeneralRequest $request){

		$data = $request->validated();

		$all_templates = $this->fetchTemplates();

		if(!in_array($data['template'], $all_templates)){
			return response(['message' => 'Invalid template','validity' => 'invalid_template'], config('global.error_code'));
		}

		try{

			if($this->invoice_settings_general_service->updateByObj($data, $this->invoice_settings_general_service->fetchRecord($data['company_id']))){
				return response(['message' => 'Settings saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}
    
}
