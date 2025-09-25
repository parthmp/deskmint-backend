<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class InvoiceSettingsGeneralController extends Controller{

	private function fetchTemplates() : array{

		$path = resource_path('resources/invoice_templates');

		$files = File::files($path);

		return $files;
	}

	public function show(Request $request){

		

	}
    
}
