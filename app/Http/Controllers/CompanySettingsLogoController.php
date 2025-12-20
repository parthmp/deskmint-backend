<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CompanySettingsLogoController extends Controller{

	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$path = 'logos/'.$company_id;

		$company = General::fetchDefaultCompanyById($company_id);

		$logo_file = $path.'/'.$company->logo;

		$image_url = '';

		if(Storage::disk('public')->exists($logo_file)){
			$image_url = Storage::disk('public')->url($logo_file);
		}

		return ['url' => $image_url];

	}
    
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

	public function destroy(Request $request){
		
		$company_id = Sanitize::input($request->input('company_id'));

		try{

			$path = 'logos/'.$company_id;

			if(Storage::disk('public')->exists($path)){
				Storage::disk('public')->deleteDirectory($path);
			}

			$company = General::fetchDefaultCompanyById($company_id);
			$company->logo = '';

			if($company->save()){
				return response(['message' => 'Logo removed successfully', 'validity' => 'remove_success'], 200);
			}

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
