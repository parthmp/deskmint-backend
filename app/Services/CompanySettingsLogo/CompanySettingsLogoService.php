<?php

namespace App\Services\CompanySettingsLogo;

use App\Repositories\Company\CompanyRepository;
use Illuminate\Support\Facades\Storage;

class CompanySettingsLogoService{

	public function __construct(private CompanyRepository $company_repository){
	}

	public function fetch(int $company_id) : string {

		$path = 'logos/'.$company_id;

		$company = $this->company_repository->fetchDefaultById($company_id);

		$logo_file = $path.'/'.$company->logo;

		$image_url = '';

		if(Storage::disk('public')->exists($logo_file)){
			$image_url = Storage::disk('public')->url($logo_file);
		}

		return $image_url;

	}

	public function updateCompanyLogo(array $data){

		//if($request->hasFile('logo')){
			$company_id = $data['company_id'];
			$file = $data['logo'];
			$path = 'logos/'.$company_id;
			
			$extension = $file->getClientOriginalExtension();
			$filename = md5(time().'_'.$file->getClientOriginalName()).'.'.$extension;
			
			Storage::disk('public')->deleteDirectory($path);
			Storage::disk('public')->makeDirectory($path);

			Storage::disk('public')->putFileAs($path, $file, $filename);

			$company = $this->company_repository->fetchDefaultById($company_id);
			$this->company_repository->updateCompanyLogoByObj($filename, $company);
			
			// $company = General::fetchDefaultCompanyById($company_id);
			// $company->logo = $filename;

			// if($company->save()){
			// 	return response(['message' => 'Logo uploaded successfully', 'validity' => 'upload_success'], 200);
			// }

			
		//}

	}

}