<?php

namespace App\Modules\Payment\Gateways\PayPal;

use App\Models\Transaction;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DatabaseOperations;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
		private ?string $currency,
		private float $amount,
		?DatabaseOperations $database_operations = null
	){
		$this->database_operations = $database_operations ?? new DatabaseOperations(new SettingsSectionRepository());
		$this->provider = new PayPalClient($this->wireUpCreds());
		$token = $this->provider->getAccessToken();
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
	 * @return Transaction
	 */
	private function createTransaction(string $order_id) : Transaction {
		return $this->database_operations->insertTransaction([
			'invoice_id'					=>	$this->invoice_id,
			'amount'						=>	$this->amount,
			'payment_method'				=>	PAYMENT_PAYPAL,
			'mode'							=>	$this->mode,
			'token_id_identifier'			=>	$order_id,
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
		
		$response = $this->provider->createOrder($this->orderData());

		if(isset($response['id']) && $response['id'] != null){
			foreach($response['links'] as $link) {
				if($link['rel'] === 'approve'){
					$transaction = $this->createTransaction($response['id']);
					$url = $link['href'];
					$this->database_operations->insertPaymentUrl($transaction->id, $response['id'], $url);
					return $url;
				}
			}
		}

		return null;
		
   	}

	/**
	 * verifyAuthenticity function
	 *
	 * @param Request $request
	 * @param string $webhook_id
	 * @return boolean
	 */
	private function verifyAuthenticity(Request $request, string $webhook_id) : bool {

		$headers = collect($request->headers->all())->map(fn($value) => $value[0])->all();

		$valid = $this->provider->verifyWebHookLocally($headers, $webhook_id, $request->getContent());

		return $valid;

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
		
		if((string) $event_type !== 'CHECKOUT.ORDER.APPROVED' && (string) $event_type !== 'PAYMENT.CAPTURE.COMPLETED' && (string) $event_type !== 'PAYMENT.CAPTURE.PENDING'){
			throw new PaymentException('Invalid data provided', 'invalid_event_type', config('global.error_code'));
		}

		try{
			
			if((string) $event_type === 'CHECKOUT.ORDER.APPROVED'){
				$this->provider->capturePaymentOrder($data['order_id']);
			}
		
		}catch(Exception $e){
			throw new Exception('something went wrong!');
		}
		
		if((string) $event_type === 'PAYMENT.CAPTURE.COMPLETED'){

			$status = $data['resource']['status'];

			if($status !== 'COMPLETED'){
				return true;
			}

			$transaction = $this->database_operations->fetchTransactionByTokenId($data['order_id']);

			if($transaction->is_payment_captured){
				return true;
			}

		}
		
		return $this->database_operations->updatePaymentTransaction($data);

		
	}

}