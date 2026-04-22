<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Http\Requests\EmailSettingsSMTP\CreateEmailSettingsSMTPRequest;
use App\Http\Requests\GenericRequest;
use App\Services\EmailSettingsSMTP\EmailSettingsSMTPService;
use Exception;
use Illuminate\Support\Facades\Cache;

class EmailSettingsSMTPController extends Controller{

	public function __construct(private EmailSettingsSMTPService $email_settings_smtp_service){
	}
    
	public function show(GenericRequest $request){

		$data = $request->validated();
		$company_id = $data['company_id'];

		try{
			return $this->email_settings_smtp_service->fetch($company_id);
		}catch(Exception $e){
			return General::wentWrong();
		}

	}
	
	public function upsert(CreateEmailSettingsSMTPRequest $request){

		$data = $request->validated();

		try{

			$this->email_settings_smtp_service->sendTestEmail($data);

			try{

				$record = $this->email_settings_smtp_service->fetchRecord($data['company_id']);

				if($this->email_settings_smtp_service->updateByObj($data, $record)){
					return response(['message' => 'Test email sent and settings saved', 'validity' => 'mail_sent_saved'], 200);
				}

				return General::wentWrong();

			}catch(Exception $e){
				return General::wentWrong();
			}

		}catch(Exception $e){
			return response(['message' => 'Unable to connect to the SMTP server', 'validity' => 'failed_to_send'], config('global.error_code'));
		}

	}

}
