<?php

namespace App\Modules\Payment\Gateways;

use App\Exceptions\PaymentException;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Gateways\PayPal\Implementation\Credentials;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Gateways\Stripe\Implementation\Credentials as StripeCredentials;
use App\Modules\Payment\Gateways\Stripe\Stripe;

class Payment {

	private InvoiceDBOperations $invoice_db_operations;
	private string $paypal_mode = '';

	public function __construct(int $company_id){
		$this->invoice_db_operations = app(InvoiceDBOperations::class);
		$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($company_id)->execRequiredSettings();
	}

	/**
	 * getPayPalCredData function
	 *
	 * @param string $currency
	 * @return Credentials
	 */
	private function getPayPalCredData(string $currency) : Credentials {
		$payment_settings = $this->invoice_db_operations->fetchPaymentSettings(PaymentGateway::PAYPAL->value);
		if(!$payment_settings){
			throw new PaymentException("no api credentials found for paypal.");
		}
		$payment_settings = json_decode($payment_settings['settings_json'], true);
		$secret = decrypt($payment_settings['secret']);
		$this->paypal_mode = $payment_settings['mode'];
		return new Credentials($payment_settings['client_id'], $payment_settings['app_id'], $secret, $payment_settings['mode'], $currency, $payment_settings['webhook_id']);
	}

	/**
	 * getStripeCredData function
	 *
	 * @return StripeCredentials
	 */
	private function getStripeCredData() : StripeCredentials {
		$payment_settings = $this->invoice_db_operations->fetchPaymentSettings(PaymentGateway::STRIPE->value);
		if(!$payment_settings){
			throw new PaymentException("no api credentials found for stripe.");
		}
		$payment_settings = json_decode($payment_settings['settings_json'], true);
		$api_secret = decrypt($payment_settings['secret']);
		$webhook_secret = decrypt($payment_settings['webhook_secret']);
		return new StripeCredentials($api_secret, $webhook_secret);
	}

	/**
	 * getPayPalMode function
	 *
	 * @return string
	 */
	public function getPayPalMode() : string {
		return $this->paypal_mode;
	}

	/**
	 * paypal function
	 *
	 * @return PayPal
	 */
	public function paypal(string $currency) : PayPal {
		$paypal = new PayPal();
		return $paypal->setCredentials($this->getPayPalCredData($currency));
	}

	/**
	 * stripe function
	 *
	 * @return Stripe
	 */
	public function stripe() : Stripe {
		$stripe = new Stripe();
		return $stripe->setCredentials($this->getStripeCredData());
	}




}