<?php

namespace App\Services\Gateway;

use App\Jobs\SendEmailJob;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\RecurringInvoice;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Gateways\Payment as GatewaysPayment;
use App\Modules\Payment\Gateways\PayPal\DTO\InvoiceRequestData;
use App\Modules\Payment\Gateways\PayPal\DTO\SubscriptionData;
use App\Modules\Payment\Gateways\PayPal\Implementation\Order;
use App\Modules\Payment\Gateways\Stripe\DTO\InvoiceRequestData as StripeInvoiceRequestData;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Repositories\Gateway\GatewayRepository;
use App\Traits\CustomMailSettings;
use Brick\Math\BigDecimal;

class GatewayService{

	use CustomMailSettings;

	public function __construct(
		private GatewayRepository $gateway_repository,
		private InvoiceDBOperations $invoice_db_operations
	){}

	/**
	 * ifInvoiceIsPaid function
	 *
	 * @param string $uuid
	 * @return boolean
	 */
	public function ifInvoiceIsPaid(string $uuid) : bool {
		return $this->gateway_repository->ifInvoiceIsPaid($uuid);
	}

	/**
	 * generateGatewayUrl function
	 *
	 * @param integer $payment_gateway
	 * @param array $data
	 * @param boolean $payment_request
	 * @return string|null
	 */
	private function generateGatewayUrl(int $payment_gateway, array $data, bool $payment_request = false) : ?string {

		$payment = new GatewaysPayment((int) $data['company_id']);

		if((int) $payment_gateway === PaymentGateway::PAYPAL->value){

			$mode = $payment->getPayPalMode();
			
			$order = new Order((string) $data['currency'], (string) $data['amount']);
			$context = new InvoiceRequestData((int) $data['currency_id'], (int) $data['company_id'], (string) $data['amount'], $mode, $data['payment_request_id'], $data['invoice_id'], (int) $data['client_id']); /** client_id is from db not paypal client id */
			$gateway_data = $payment->paypal((string) $data['currency'])->setContext($context)->createOrder($order)->getInvoiceOrRequestData($payment_request);
			
			return $gateway_data['invoice_or_request_payment_url'];

		}else if((int) $payment_gateway === PaymentGateway::STRIPE->value){

			$context = new StripeInvoiceRequestData((string) $data['currency'], (int) $data['company_id'], (int) $data['currency_id'], (string) $data['amount'], $data['payment_request_id'], $data['invoice_id'], $data['invoice_number'], (int) $data['client_id']);
			$gateway_data = $payment->stripe()->setContext($context)->getInvoiceOrRequestData($payment_request);

			return $gateway_data['invoice_or_request_payment_url'];

		}

		return null;

	}

	/**
	 * generateSubscriptionUrl function
	 *
	 * @param RecurringInvoice $recurring_invoice
	 * @return string|null
	 */
	private function generateSubscriptionUrl(RecurringInvoice $recurring_invoice) : ?string {

		$payment = new GatewaysPayment((int) $recurring_invoice->company_id);

		if((int) $recurring_invoice->payment_gateway === PaymentGateway::PAYPAL->value){

			$context = new SubscriptionData($recurring_invoice);

			return $payment->paypal((string) $recurring_invoice->code)->setContext($context)->getSubscriptionUrl();

		}else if((int) $recurring_invoice->payment_gateway === PaymentGateway::STRIPE->value){
			
			$context = new SubscriptionData($recurring_invoice);
			return $payment->stripe()->setContext($context)->getSubscriptionUrl();

		}

		return null;

	}

	/**
	 * sendUrlGenerationFailedEmail function
	 *
	 * @param integer $payment_gateway
	 * @return void
	 */
	private function sendUrlGenerationFailedEmail(int $payment_gateway) : void {

		$data = [
			'payment_gateway'		=>  PaymentGateway::getLabelByValue($payment_gateway)
		];

		$info = $this->invoice_db_operations->fetchAdminEmails();

		if($info){

			$first_email = $info[0]['email'];
			$first_name = $info[0]['name'];

			array_shift($info);

			$info = array_values($info);

			SendEmailJob::dispatch(
				to: $first_email,
				to_name: $first_name,
				mailable_class: \App\Mail\SendFailedPaymentURLGenerationEmail::class,
				mailable_data: [$data],
				smtp: $this->smtpSettings(),
				cc: $info
			);
		}

	}

	/**
	 * generatePaymentUrl function
	 *
	 * @param Invoice|PaymentRequest $object
	 * @return string|null
	 */
	public function generatePaymentUrl(Invoice|PaymentRequest $object) : ?string {

		if((int) $object->payment_gateway !== PaymentGateway::NONE->value){

			$is_invoice = true;
			$is_payment_request = false;
			$payment_gateway_url = '';

			if(!($object instanceof Invoice)){
				$is_invoice = false;
				$is_payment_request = true;
			}

			$data['currency'] = $object->currency->code;
			$data['amount'] = ($is_invoice) ? $object->balance_due : $object->amount;
			$data['invoice_id'] = ($is_invoice) ? $object->id : null;
			$data['invoice_number'] = ($is_invoice) ? $object->invoice_number : null;
			$data['payment_request_id'] = (!$is_invoice) ? $object->id : null;
			$data['company_id'] = $object->company_id;
			$data['currency_id'] = $object->currency_id;
			$data['client_id'] = $object->client_id;

			$payment_gateway_url = $this->generateGatewayUrl((int) $object->payment_gateway, $data, $is_payment_request);

			if(!$payment_gateway_url){
				//send an email to admins to notify the failure.
				$this->sendUrlGenerationFailedEmail((int) $object->payment_gateway);
			}

			return $payment_gateway_url;

		}

		return null;

	}
	
	/**
	 * fetchNotPaidInvoiceByUuid function
	 *
	 * @param string $uuid
	 * @return Invoice|null
	 */
	public function fetchInvoiceByUuid(string $uuid) : ?Invoice {
		return $this->gateway_repository->fetchInvoiceByUuid($uuid);
	}

	/**
	 * fetchExistingPaymentUrl function
	 *
	 * @param integer $invoice_id
	 * @return string|null
	 */
	public function fetchExistingPaymentUrl(int $invoice_id, int $currency_id) : ?string {

		$transaction = $this->gateway_repository->fetchTransactionOfPast($invoice_id, $currency_id);
		
		if(!$transaction){
			return null;
		}

		$invoice = $this->gateway_repository->fetchInvoiceById($invoice_id);
		
		if(!$invoice){
			return null;
		}

		$invoice_balance_due = BigDecimal::of($invoice->balance_due);
		$transaction_amount = BigDecimal::of($transaction->amount);
		
		if((int) $invoice->payment_gateway !== (int) $transaction->payment_gateway || !$invoice_balance_due->isEqualTo($transaction_amount)){
			return null;
		}

		return $transaction->payment_url;

	}

	/**
	 * fetchRequestByUuid function
	 *
	 * @param string $uuid
	 * @return PaymentRequest|null
	 */
	public function fetchRequestByUuid(string $uuid) : ?PaymentRequest {
		return $this->gateway_repository->fetchRequestByUuid($uuid);
	}

	/**
	 * fetchExistingPaymentUrlForRequest function
	 *
	 * @param integer $pr_id
	 * @param integer $currency_id
	 * @return string|null
	 */
	public function fetchExistingPaymentUrlForRequest(int $pr_id, int $currency_id) : ?string {

		$transaction = $this->gateway_repository->fetchTransactionOfPastForRequests($pr_id, $currency_id);
		
		if(!$transaction){
			return null;
		}

		$pr = $this->gateway_repository->fetchPaymentRequestById($pr_id);
		
		if(!$pr){
			return null;
		}

		$pr_amount = BigDecimal::of($pr->amount);
		$transaction_amount = BigDecimal::of($transaction->amount);
		
		if((int) $pr->payment_gateway !== (int) $transaction->payment_gateway || !$pr_amount->isEqualTo($transaction_amount)){
			return null;
		}

		return $transaction->payment_url;

	}

	/**
	 * fetchRecurringInvoiceByUuid function
	 *
	 * @param string $uuid
	 * @param array $selects
	 * @return RecurringInvoice|null
	 */
	public function fetchRecurringInvoiceByUuid(string $uuid, array $selects = ['*']) : ?RecurringInvoice {
		return $this->gateway_repository->fetchRecurringInvoiceByUuid($uuid, $selects);
	}

	/**
	 * fetchCachedUrlForRecurringInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @param integer $payment_gateway
	 * @param string $total
	 * @param integer $currency_id
	 * @return string|null
	 */
	public function fetchCachedUrlForRecurringInvoice(int $company_id, int $recurring_invoice_id, int $payment_gateway, string $total, int $currency_id) : ?string {
		return $this->gateway_repository->fetchCachedUrlForRecurringInvoice($company_id, $recurring_invoice_id, $payment_gateway, $total, $currency_id);
	}

	/**
	 * generateRecurringInvoiceUrl function
	 *
	 * @param RecurringInvoice $recurring_invoice
	 * @return string
	 */
	public function generateRecurringInvoiceUrl(RecurringInvoice $recurring_invoice) : string {
		
		$payment_gateway_url = $this->generateSubscriptionUrl($recurring_invoice);
		
		if(!$payment_gateway_url){
			//send an email to admins to notify the failure.
			$this->sendUrlGenerationFailedEmail((int) $recurring_invoice['payment_gateway']);
			return '';
		}

		return $payment_gateway_url;

	}

}