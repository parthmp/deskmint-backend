<?php

namespace App\Services\Gateway;

use App\Jobs\SendEmailJob;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\RecurringInvoice;
use App\Models\Transaction;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Modules\Payment\Payment;
use App\Repositories\Gateway\GatewayRepository;
use App\Traits\CustomMailSettings;
use Brick\Math\BigDecimal;
use Exception;

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
	 * @param boolean $subscription
	 * @return string|null
	 */
	private function generateGatewayUrl(int $payment_gateway, array $data, bool $subscription = false, ?array $recurring_invoice = null) : ?string {

		if((int) $payment_gateway === PaymentGateway::PAYPAL->value){

			$gateway = new PayPal($data['company_id'], $data['currency_id'], $data['invoice_id'], $data['pr_id'], $data['user_id'], $data['client_id'], $data['app_id'], $data['secret'], $data['mode'], $data['currency'], (string) $data['amount'], null, null, $subscription);

			$gateway = $gateway->setRecurringInvoiceData($recurring_invoice);

		}else if((int) $payment_gateway === PaymentGateway::STRIPE->value){
			$gateway = new Stripe($data['company_id'], $data['currency_id'], $data['invoice_id'], $data['pr_id'], $data['user_id'], $data['secret'], $data['currency'], (string) $data['amount']);
		}else{
			return null;
		}

		$payment = new Payment($gateway);
		return $payment->paymentURL();

		// $payment = match($payment_gateway){

		// 	PaymentGateway::PAYPAL->value 	=> new Payment(),
		// 	PaymentGateway::STRIPE->value 	=> new Payment(new Stripe($data['company_id'], $data['currency_id'], $data['invoice_id'], $data['pr_id'], $data['user_id'], $data['secret'], $data['currency'], (string) $data['amount'])),
		// 	default			=>	null
		// };
		
		// if(!$payment){
		// 	return null;
		// }

		// return $payment->paymentURL();

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
			$payment_gateway_url = '';
			if($object instanceof Invoice){
				$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($object->company_id)->setInvoiceId($object->id)->execRequiredSettings();
			}else{
				$is_invoice = false;
				$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($object->company_id)->execRequiredSettings();
			}
			
			$payment_settings = $this->invoice_db_operations->fetchPaymentSettings((int) $object->payment_gateway);
			
			if(!$payment_settings){
				logger('something went wrong with payment settings data -> '.json_encode($payment_settings));
				throw new Exception("something went wrong");
			}

			$payment_settings = json_decode($payment_settings['settings_json'], true);
			$payment_settings['currency'] = $object->currency->code;
			$payment_settings['amount'] = ($is_invoice) ? $object->balance_due : $object->amount;
			$payment_settings['secret'] = decrypt($payment_settings['secret']);
			$payment_settings['invoice_id'] = ($is_invoice) ? $object->id : null;
			$payment_settings['pr_id'] = (!$is_invoice) ? $object->id : null;
			$payment_settings['company_id'] = $object->company_id;
			$payment_settings['currency_id'] = $object->currency_id;
			$payment_settings['user_id'] = $object->client_id;

			$payment_gateway_url = $this->generateGatewayUrl((int) $object->payment_gateway, $payment_settings);

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
	public function fetchExistingPaymentUrl(int $invoice_id) : ?string {

		$transaction = $this->gateway_repository->fetchTransactionOfPast($invoice_id);
		
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
	 * @return string|null
	 */
	public function fetchExistingPaymentUrlForRequest(int $pr_id) : ?string {

		$transaction = $this->gateway_repository->fetchTransactionOfPastForRequests($pr_id);
		
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
	 * @param array $recurring_invoice
	 * @return string
	 */
	public function generateRecurringInvoiceUrl(array $recurring_invoice) : string {
		

		$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId((int) $recurring_invoice['company_id'])->execRequiredSettings();
		$payment_settings = $this->invoice_db_operations->fetchPaymentSettings((int) $recurring_invoice['payment_gateway']);

		$payment_settings = json_decode($payment_settings['settings_json'], true);
		$payment_settings['currency'] = $recurring_invoice['code'];
		$payment_settings['amount'] = $recurring_invoice['total'];
		$payment_settings['secret'] = decrypt($payment_settings['secret']);
		$payment_settings['invoice_id'] = null;
		$payment_settings['pr_id'] = null;
		$payment_settings['company_id'] = $recurring_invoice['company_id'];
		$payment_settings['currency_id'] = $recurring_invoice['currency_id'];
		$payment_settings['user_id'] = $recurring_invoice['client_id'];

		$payment_gateway_url = $this->generateGatewayUrl((int) $recurring_invoice['payment_gateway'], $payment_settings, true, $recurring_invoice);
		
		if(!$payment_gateway_url){
			//send an email to admins to notify the failure.
			$this->sendUrlGenerationFailedEmail((int) $recurring_invoice['payment_gateway']);
			return '';
		}

		return $payment_gateway_url;

	}

}