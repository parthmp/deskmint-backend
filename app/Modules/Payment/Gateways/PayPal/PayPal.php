<?php

namespace App\Modules\Payment\Gateways\PayPal;

use App\Modules\Payment\Gateways\PayPal\Implementation\Credentials;
use App\Modules\Payment\Gateways\PayPal\Implementation\Order;
use App\Modules\Payment\Gateways\PayPal\Implementation\TransactionReference;
use Srmklive\PayPal\Services\PayPal as Provider;

class PayPal {

	private ?array $order_response = null;
	private ?string $invoice_payment_url = null;
	private ?string $order_response_id = null;

	public function __construct(
		private Provider $provider
	){}

	/**
	 * setCredentials function
	 *
	 * @param Credentials $credentials
	 * @return self
	 */
	public function setCredentials(Credentials $credentials) : self {
		$this->provider = $credentials->getProvider();
		return $this;
	}

	/**
	 * createOrder function
	 *
	 * @param Order $order
	 * @return self
	 */
	public function createOrder(Order $order) : self {
		$this->order_response = $order->createOrder($this->provider);
		return $this;
	}

	/**
	 * getOrderResponse function
	 *
	 * @return array
	 */
	public function getOrderResponse() : array {
		return $this->order_response;
	}

	/**
	 * setInvoicePaymentData function
	 *
	 * @return self
	 */
	public function setInvoicePaymentData(TransactionReference $transaction_reference) : self {

		$ref = $transaction_reference->create();

		if(isset($this->order_response['id']) && $this->order_response['id'] != null){
			foreach($this->order_response['links'] as $link) {
				if($link['rel'] === 'approve'){
					$this->order_response_id = $this->order_response['id'];
					$this->invoice_payment_url = $link['href'];
					break;
				}
			}
		}

		return $this;

	}

	/**
	 * getInvoicePaymentData function
	 *
	 * @return array
	 */
	public function getInvoicePaymentData() : array {
		return [
			'order_response_id'		=>	$this->order_response_id,
			'invoice_payment_url'	=>	$this->invoice_payment_url,
		];
	}
	


}