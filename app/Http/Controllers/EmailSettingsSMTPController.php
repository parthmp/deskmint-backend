<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\SettingsSection;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailSettingsSMTPController extends Controller{
    
	use SettingsDefault;

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$email_smtp = SettingsSection::where([['type', '=', ESC_EMAIL_SMTP_TYPE], ['company_id', '=', $company_id]])->first();

		try{

			if(!$email_smtp){
				return $this->getDefaultEmailSMTPSettings();
			}

			$settings = json_decode($email_smtp->settings_json, true);

			try{
				$settings['password'] = decrypt($settings['password']);
			}catch(Exception $e){
				$settings['password'] = '';
			}

			return $settings;

		}catch(Exception $e){

			return General::wentWrong();

		}
	}

	public function saveOrUpdate(Request $request){

		$v = Validator::make($request->all(), [
			'host'					=>		'required',
			'port'					=>		'required',
			'username'				=>		'required',
			'mail_from_address'		=>		'required|email',
			'mail_from_name'		=>		'required',
			'reply_to_address'		=>		'required|email',
			'encryption'			=>		'required|in:tls,ssl',
			'test_email_address'	=>		'required|email'
		]);

		if($v->fails()){
			return response(['message' => 'Please fill the required fields', 'validity' => 'invalid_data'], config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));

		$host = Sanitize::input($request->input('host'));
		$port = Sanitize::input($request->input('port'));
		$username = Sanitize::input($request->input('username'));
		$mail_from_address = Sanitize::input($request->input('mail_from_address'));
		$mail_from_name = Sanitize::input($request->input('mail_from_name'));
		$reply_to_address = Sanitize::input($request->input('reply_to_address'));
		$encryption = Sanitize::input($request->input('encryption'));
		$test_email_address = Sanitize::input($request->input('test_email_address'));
		
		$password = '';

		if($request->filled('password')){
			$password = $request->input('password');
		}
		
		try{

			config([
				'mail.mailers.smtp.host' => $host,
				'mail.mailers.smtp.port' => $port,
				'mail.mailers.smtp.username' => $username,
				'mail.mailers.smtp.password' => $password,
				'mail.mailers.smtp.encryption' => $encryption,
				'mail.from.address' => $mail_from_address,
				'mail.from.name' => $mail_from_name,
				'mail.reply_to.address' => $reply_to_address,
				'mail.reply_to.name' => $mail_from_name
			]);

			Mail::raw('This is a test email from your SMTP configuration.', function ($message) use ($test_email_address) {
				$message->to($test_email_address)->subject('DeskMint - SMTP Test Email');
			});

			try{

				$email_smtp = SettingsSection::where([['type', '=', ESC_EMAIL_SMTP_TYPE], ['company_id', '=', $company_id]])->first();

				if(!$email_smtp){
					$email_smtp = new SettingsSection();
					$email_smtp->company_id = $company_id;
					$email_smtp->type = ESC_EMAIL_SMTP_TYPE;
				}

				$json_string = json_encode([
					'host'					=>	$host,
					'port'					=>	$port,
					'username'				=>	$username,
					'password'				=>	encrypt($password),
					'encryption'			=>	$encryption,
					'mail_from_address'		=>	$mail_from_address,
					'mail_from_name'		=>	$mail_from_name,
					'reply_to_address'		=>	$reply_to_address,
					'test_email_address'	=>	$test_email_address
				]);

				$email_smtp->settings_json = $json_string;

				$email_smtp->save();

				return response(['message' => 'Test email sent and settings saved', 'validity' => 'mail_sent_saved'], 200);

			}catch(Exception $e){
				return General::wentWrong();
			}

		}catch(Exception $e){
			return response(['message' => 'Unable to connect to the SMTP server', 'validity' => 'failed_to_send'], config('global.error_code'));
		}

	}

}
