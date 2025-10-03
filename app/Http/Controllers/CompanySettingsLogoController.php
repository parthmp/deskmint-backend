<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanySettingsLogoController extends Controller{
    
	public function saveOrUpdate(Request $request){

		$validator = Validator::make($request->all(), [
			'logo' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120'
		]);

		if($validator->fails()){
			return response(['message' => 'Unable to upload - Invalid file', 'validity' => 'invalid_file'], config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));

		try{

			if($request->hasFile('logo')){

				$file = $request->file('logo');
				$path = 'logos/'.$company_id;
				
				$extension = $file->getClientOriginalExtension();
				$filename = md5(time().'_'.$file->getClientOriginalName()).'.'.$extension;
				
				Storage::disk('public')->deleteDirectory($path);
				Storage::disk('public')->makeDirectory($path);

				Storage::disk('public')->putFileAs($path, $file, $filename);
				
				$company = General::fetchDefaultCompanyById($company_id);
				$company->logo = $filename;

				if($company->save()){
					return response(['message' => 'Logo uploaded successfully', 'validity' => 'upload_success'], 200);
				}

				
			}

		}catch(Exception $e){

			return General::wentWrong();

		}

	}

}
