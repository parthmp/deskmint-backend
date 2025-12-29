<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\EmailSettingsContent\CreateEmailSettingsContentRequest;
use App\Services\EmailSettingsContent\EmailSettingsContentService;
use Exception;
use Illuminate\Http\Request;

class EmailSettingsContentController extends Controller{

	/**
	 * __construct function
	 *
	 * @param EmailSettingsContentService $email_settings_content_service
	 */
	public function __construct(private EmailSettingsContentService $email_settings_content_service){
	}


	public function show(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{
			return $this->email_settings_content_service->fetch($company_id);
		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}


	public function upsert(CreateEmailSettingsContentRequest $request){

		$data = $request->validated();

		$email_content = $this->email_settings_content_service->fetchRecord($data['company_id']);

		try{

			if($this->email_settings_content_service->updateByObj($data, $email_content)){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

			return General::wentWrong();

		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
