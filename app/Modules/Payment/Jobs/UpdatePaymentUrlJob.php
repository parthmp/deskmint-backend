<?php

namespace App\Modules\Payment\Jobs;

use App\Models\Invoice;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Modules\Payment\Payment;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdatePaymentUrlJob implements ShouldQueue
{
    use Queueable;

	public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        private Invoice $invoice,
        private string $gateway_url_identifier,
        private array $payment_settings,
    ){}

	/**
	 * updatePaymentURL function
	 *
	 * @param Invoice $invoice
	 * @param string $gateway_url_identifier
	 * @param array $data
	 * @return boolean
	 */
	private function updatePaymentURL(Invoice $invoice, string $gateway_url_identifier, array $data) : bool {
		
		$payment = match((int) $invoice->payment_method){

			PAYMENT_PAYPAL 	=> new Payment(new PayPal($invoice->id, $data['client_id'], $data['app_id'], $data['secret'], $data['mode'], $data['currency'], (float) $data['amount'])),
			PAYMENT_STRIPE 	=> new Payment(new Stripe($invoice->id, $data['secret'], $data['currency'], (float) $data['amount'])),
			default 		=> null
		};
		
		if($payment !== null){
			return $payment->updateUrl($gateway_url_identifier);
		}

		return false;
		
	}

    public function handle() : void {

		$updated = $this->updatePaymentURL($this->invoice, $this->gateway_url_identifier, $this->payment_settings);

		if(!$updated){
            throw new Exception('unable to update the payment url, retrying...');
        }

    }

   
}