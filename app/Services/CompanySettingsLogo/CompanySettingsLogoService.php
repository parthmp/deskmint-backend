<?php

namespace App\Services\CompanySettingsLogo;

use App\Repositories\Company\CompanyRepository;
use Exception;
use Illuminate\Support\Facades\Storage;

class CompanySettingsLogoService{

	public function __construct(private CompanyRepository $company_repository){
	}

	/**
	 * fetch function
	 *
	 * @param integer $company_id
	 * @return string
	 */
	public function fetch(int $company_id) : string {

		$path = 'logos/'.$company_id;

		$company = $this->company_repository->fetchDefaultById($company_id);

		$logo_file = $path.'/'.$company->logo;

		$image_url = '';

		if(Storage::disk('public')->exists($logo_file) && trim($company->logo) !== ''){
			$image_url = Storage::disk('public')->url($logo_file);
		}

		return $image_url;

	}

	/**
	 * update function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function update(array $data) : bool {
		
		$company_id = $data['company_id'];
		$file = $data['logo'];
		$path = 'logos/'.$company_id;
		
		$extension = $file->getClientOriginalExtension();
		$filename = md5(time().'_'.$file->getClientOriginalName()).'.'.$extension;
		
		//do not remove logo file as we are using it for invoice snapshot.
		// Storage::disk('public')->deleteDirectory($path);
		Storage::disk('public')->makeDirectory($path);

		Storage::disk('public')->putFileAs($path, $file, $filename);

		$company = $this->company_repository->fetchDefaultById($company_id);
		return $this->company_repository->updateCompanyLogoByObj($filename, $company);
		

	}

	/**
	 * remove function
	 *
	 * @param integer $company_id
	 * @return boolean
	 */
	public function remove(int $company_id) : bool {
		
		try{

			$path = 'logos/'.$company_id;

			//do not remove logo file as we are using it for invoice snapshot.
			// if(Storage::disk('public')->exists($path)){
			// 	Storage::disk('public')->deleteDirectory($path);
			// }

			$company = $this->company_repository->fetchDefaultById($company_id);
			return $this->company_repository->updateCompanyLogoByObj('', $company);

		}catch(Exception $e){
			throw new Exception('failed to remove logo');
		}
	}

}