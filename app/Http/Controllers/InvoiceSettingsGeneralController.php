<?php

namespace App\Http\Controllers;

use App\Helpers\General;
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

			$settings = SettingsSection::where('type', '=', 'invoice')->first();

			if($settings){
				return $settings->settings_json;
			}

			return 	[
						'settings' 	=> $this->getDefaultInvoiceGeneralSettings(),
						'templates'	=>	$this->fetchTemplates()
					];
					
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function saveOrUpdate(Request $request){

		// $v = Validator::make($request->all(), [
		// 	'template'	=>	'required',

		// ]);

	}
    
}
