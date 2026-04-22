<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait CustomMailSettings{

	private function smtpSettings() : array {

		$cached = Cache::rememberForever('smtp_settings', function () {
					$row = DB::table('settings_section')
						->where('type', ESC_EMAIL_SMTP_TYPE)
						->first();

					return $row ? json_decode($row->settings_json, true) : [];
				});

		$password = null;

		if(!empty($cached['password'])){
			try{
				$password = decrypt($cached['password']);
			}catch(\Throwable $e){
				$password = null;
			}
		}

		return [
			'host' 			=> $cached['host'] ?? config('mail.mailers.smtp.host'),
			'port' 			=> $cached['port'] ?? config('mail.mailers.smtp.port'),
			'username' 		=> $cached['username'] ?? config('mail.mailers.smtp.username'),
			'password' 		=> $password ?? config('mail.mailers.smtp.password'),
			'encryption' 	=> $cached['encryption'] ?? config('mail.mailers.smtp.encryption'),
			'from_address' 	=> $cached['mail_from_address'] ?? config('mail.from.address'),
			'from_name' 	=> $cached['mail_from_name'] ?? config('mail.from.name'),
			'reply_to' 		=> $cached['reply_to_address'] ?? null,
		];

	}

}