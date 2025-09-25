<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\SettingsInvoice;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsGeneralController extends Controller{

	use SettingsDefault;

	private function fetchTemplates() : array{

		$path = resource_path('../resources/invoice_templates');

		$files = File::files($path);

		$fileArray = array_map(function($file){
			$file_name = $file->getFilename();
			return pathinfo($file_name, PATHINFO_FILENAME);
		}, $files);

		return $fileArray;
	}

	public function show(Request $request){

		try{

			$company_id = Sanitize::input($request->input('company_id'));

			$settings = SettingsSection::where([['type', '=', 'invoice_general'], ['company_id', '=', $company_id]])->first();

			if($settings){
				return 	[
						'settings' 	=> 	json_decode($settings->settings_json, true),
						'templates'	=>	$this->fetchTemplates()
					];
			}

			return 	[
						'settings' 	=> 	$this->getDefaultInvoiceGeneralSettings(),
						'templates'	=>	$this->fetchTemplates()
					];
					
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function saveOrUpdate(Request $request){

		$v = Validator::make($request->all(), [
			'template'			=>	'required',
			'font_size'			=>	'required|numeric',
			'logo_size'			=>	'required|numeric',
			'primary_color'		=>	'required|hex_color',
			'secondary_color'	=>	'required|hex_color'
		]);
		
		if($v->fails()){
			return response(['message' => 'Please fill in required fields', 'errors' => $request->all(),'validity' => 'invalid_data'], config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));

		$template = Sanitize::input($request->input('template'));
		$font_size = (int)Sanitize::input($request->input('font_size'));
		$logo_size = (int)Sanitize::input($request->input('logo_size'));
		$primary_color = Sanitize::input($request->input('primary_color'));
		$secondary_color = Sanitize::input($request->input('secondary_color'));

		$e_invoice = $request->boolean('e_invoice');

		$json = json_encode([
				'template'				=>	$template,
				'font_size'				=>	$font_size,
				'logo_size'				=>	$logo_size,
				'primary_color'			=>	$primary_color,
				'secondary_color'		=>	$secondary_color,
				'e_invoice_on'			=>	$e_invoice
			]);
		
		$setting = SettingsSection::where([['type', '=', 'invoice_general'], ['company_id', '=', $company_id]])->first();

		if($setting){
			$obj = $setting;
		}else{
			$obj = new SettingsSection();
			$obj->company_id = $company_id;
			$obj->type = 'invoice_general';
		}

		$obj->settings_json = $json;

		if($obj->save()){
			return response(['message' => 'Settings saved successfully', 'validity' => 'saved_success'], 200);
		}



	}
    
}
