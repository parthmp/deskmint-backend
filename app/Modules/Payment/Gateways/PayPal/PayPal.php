<?php

namespace App\Modules\Payment\Gateways\PayPal;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Exceptions\PaymentException;
use Exception;
use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPal implements PaymentGatewayInterface{

	private DatabaseOperations $database_operations;
	private PayPalClient $provider;

	public function __construct(
		private string $invoice_id,
		private string $client_id,
		private string $app_id,
		private string $secret,
		private string $mode,
		private string $currency,
		private float $amount
	){
		$this->database_operations = new DatabaseOperations();
		$this->provider = new PayPalClient($this->wireUpCreds());
	}

	/**
	 * wireUpCreds function
	 *
	 * @return array
	 */
	private function wireUpCreds() : array {
		return [
			'payment_action'	=>	'Sale',
			'currency'			=>	$this->currency,
			//'notify_url'		=>	env('APP_URL').PAYMENT_NOTIFICATION_URL,
			'notify_url'		=>	'',
			'validate_ssl'		=>	true,
			'mode'				=>	$this->mode,
			'locale'			=>	'en_US',
			'sandbox' => [
				'client_id'         => $this->client_id,
				'client_secret'     => $this->secret,
				'app_id'            => $this->app_id
			],
			'live' => [
				'client_id'         => $this->client_id,
				'client_secret'     => $this->secret,
				'app_id'            => $this->app_id
			],
		];
	}

	/**
	 * orderData function
	 *
	 * @return array
	 */
	private function orderData() : array {
		return [
			"intent" => "CAPTURE",
			"purchase_units" => [
				[
					"amount" => [
						"currency_code" => $this->currency,
						"value" 		=> $this->amount
					]
				]
			],
			"application_context" => [
				"cancel_url" => env('APP_URL').PAYMENT_CANCEL_URL,
				"return_url" => env('APP_URL').PAYMENT_SUCCESS_URL,
			]
		];
	}

	/**
	 * createTransaction function
	 *
	 * @param string $order_id
	 * @return boolean
	 */
	private function createTransaction(string $order_id) : bool {
		return $this->database_operations->insertTransaction([
			'invoice_id'					=>	$this->invoice_id,
			'amount'						=>	$this->amount,
			'payment_method'				=>	PAYMENT_PAYPAL,
			'mode'							=>	$this->mode,
			'token_id_identifier'			=>	$order_id,
			'payment_approved_details'		=>	null,
			'payment_captured_details'		=>	null,
			'is_approved'					=>	0,
			'is_payment_captured'			=>	0
		]);
	}

	/**
	 * generateURL function
	 *
	 * @return string|null
	 */
	public function generateURL() : ?string {
		
		$paypal_token = $this->provider->getAccessToken();

		$response = $this->provider->createOrder($this->orderData());
		
		if(isset($response['id']) && $response['id'] != null){
			foreach($response['links'] as $link) {
				if($link['rel'] === 'approve'){
					$this->createTransaction($response['id']);
					return $link['href'];
				}
			}
		}

		return null;
		
   	}

	private function verifyAuthenticity(Request $request, string $webhook_id) : bool {
		
		$this->provider->getAccessToken();

		$verified = $this->provider->verifyWebHook([
			'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
			'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
			'cert_url'          => $request->header('PAYPAL-CERT-URL'),
			'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
			'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
			'webhook_id'        => $webhook_id,
			'webhook_event'     => $request->all()
		]);

		if(isset($verified['verification_status'])){
			return $verified['verification_status'] === 'SUCCESS';
		}

		return false;

	}

	/**
	 * handlePayment function
	 *
	 * @return boolean
	 */
	public function handlePayment(array $data, Request $request) : bool {

		if(!$this->verifyAuthenticity($request, $data['webhook_id'])){
			throw new PaymentException('unauthorized', 'unauthorized', 401);
		}

		$event_type = $data['event_type'] ?? null;

		if($event_type !== 'CHECKOUT.ORDER.APPROVED' && $event_type !== 'PAYMENT.CAPTURE.COMPLETED'){
			throw new PaymentException('Invalid data provided', 'invalid_event_type', config('global.error_code'));
		}

		try{

			if($event_type === 'CHECKOUT.ORDER.APPROVED'){
				$this->provider->capturePaymentOrder($data['order_id']);
			}
		
		}catch(Exception $e){
			throw new Exception('something went wrong!');
		}

		
		return $this->database_operations->updatePaymentTransaction($data);

		
	}

}