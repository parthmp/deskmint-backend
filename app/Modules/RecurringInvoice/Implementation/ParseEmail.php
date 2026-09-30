<?php

namespace App\Modules\RecurringInvoice\Implementation;

use App\Enums\RecurringInvoices\Frequencies;
use App\Helpers\General;
use App\Modules\Payment\Enums\PaymentGateway;
use Illuminate\Support\Facades\URL;

class ParseEmail {

	private array $keys = [
		'{$client_first_name}'	=>	'first_name',
		'{$client_last_name}'	=>	'last_name',
		'{$frequency}'			=>	'frequency',
		'{$total}'				=>	'total',
		'{$currency}'			=>	'currency',
		'{$subscription_url}'	=>	''
	];

	/**
	 * parse function
	 *
	 * @param array $data
	 * @param string $content
	 * @return string
	 */
	public function parse(array $data, string $content) : string {
		
		$parsed = $content;

		foreach($this->keys as $key => $value){
			
			$replace = '';

			if($key === '{$subscription_url}'){
				
				$url = URL::signedRoute('recurring_invoice.activate', ['uuid' => $data['uuid']]);
				$replace = $url;

			}else if($key === '{$frequency}'){

				if(Frequencies::CUSTOM->value !== (int) $data['frequency']){
					$replace = Frequencies::getLabelByValue((int) $data['frequency']);
				}else{
					$replace = $data['custom_frequency_days'].' days';
				}

			}else{
				$replace = $data[$value];
			}

			$parsed = str_ireplace($key, $replace, $parsed);
			
		}


		if((int) $data['payment_gateway'] === PaymentGateway::NONE->value){
			$parsed = General::removeBetween('[{online-payment-start}]', '[{online-payment-end}]', $parsed);
		}

		$parsed = str_ireplace('[{online-payment-start}]', '', $parsed);
		$parsed = str_ireplace('[{online-payment-end}]', '', $parsed);

		return $parsed;

	}

}