<?php

namespace App\Modules\RecurringInvoice\Implementation;

use App\Jobs\SendGenericEmailJob;
use Illuminate\Support\Facades\Bus;

class SendEmail {

	public function __construct(
		private ParseEmail $parse_email
	){}

	/**
	 * sendEmail function
	 *
	 * @param array $data
	 * @param string $content
	 * @return void
	 */
	public function sendEmail(array $data, string $content) : void {
		
		for($z = 0 ; $z < count($data) ; $z++){
			
			$parsed = $this->parse_email->parse($data[$z], $content);

			Bus::chain([
				new SendGenericEmailJob([
					'subject'		=>	'Recurring invoices scheduled',
					'email'			=>	$data[$z]['email'],
					'first_name'	=>	$data[$z]['first_name'],
					'last_name'		=>	$data[$z]['last_name'],
					'content'		=>	$parsed,
				]),
				//add status change job here.
			])->dispatch();
			
		}

	}

}