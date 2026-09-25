<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailSettingsContentException;
use App\Helpers\General;
use App\Http\Requests\EmailSettingsContent\CreateEmailSettingsContentRequest;
use App\Http\Requests\EmailSettingsContent\EmailSettingsContentUpsertRequest;
use App\Http\Requests\GenericRequest;
use App\Services\EmailSettingsContent\EmailSettingsContentService;
use Exception;
use Illuminate\Http\Request;

class EmailSettingsContentController extends Controller{

	/**
	 * __construct function
	 *
	 * @param EmailSettingsContentService $email_settings_content_service
	 */
	public function __construct(
		private EmailSettingsContentService $email_settings_content_service
	){}


	public function show(EmailSettingsContentUpsertRequest $request){

		$data = $request->validated();
		
		try{
			
			$record = $this->email_settings_content_service->fetch((int) $data['company_id'], (string) $data['key']);
			if(!$record){
				return General::wentWrong();
			}

			return $record;

		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}


	public function upsert(EmailSettingsContentUpsertRequest $request){

		$data = $request->validated();

		try{

			$filtered_data = $this->email_settings_content_service->filterAndValidateContentData((array) $data['data'], (string) $data['key']);

			$this->email_settings_content_service->save((int) $data['company_id'], (array) $filtered_data, (string) $data['key']);

			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);

		}catch(EmailSettingsContentException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

}
