<?php

namespace App\Services\Payment;

use App\Jobs\SendEmailJob;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Gateways\Stripe\Stripe;
use App\Modules\Payment\Payment;
use App\Repositories\Payment\PaymentRepository;
use App\Traits\CustomMailSettings;
use Brick\Math\BigDecimal;
use Exception;

class PaymentService{

	use CustomMailSettings;

	public function __construct(
		private PaymentRepository $payment_repository,
		private InvoiceDBOperations $invoice_db_operations
	){}

	/**
	 * ifInvoiceIsPaid function
	 *
	 * @param string $uuid
	 * @return boolean
	 */
	public function ifInvoiceIsPaid(string $uuid) : bool {
		return $this->payment_repository->ifInvoiceIsPaid($uuid);
	}

	/**
	 * generateGatewayUrl function
	 *
	 * @param integer $payment_gateway
	 * @param array $data
	 * @return string|null
	 */
	private function generateGatewayUrl(int $payment_gateway, array $data) : ?string {

		$payment = match($payment_gateway){

			PaymentGateway::PAYPAL->value 	=> new Payment(new PayPal($data['company_id'], $data['invoice_id'], $data['client_id'], $data['app_id'], $data['secret'], $data['mode'], $data['currency'], (float) $data['amount'])),
			PaymentGateway::STRIPE->value 	=> new Payment(new Stripe($data['company_id'], $data['invoice_id'], $data['secret'], $data['currency'], (float) $data['amount'])),
			default			=>	null
		};
		
		if(!$payment){
			return null;
		}

		return $payment->paymentURL();

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
	 * @param Invoice $invoice
	 * @return string|null
	 */
	public function generatePaymentUrl(Invoice $invoice) : ?string {

		if((int) $invoice->payment_gateway !== PaymentGateway::NONE->value){

			$payment_gateway_url = '';
			$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($invoice->company_id)->setInvoiceId($invoice->id)->execRequiredSettings();
			$payment_settings = $this->invoice_db_operations->fetchPaymentSettings((int) $invoice->payment_gateway);
			
			if(!$payment_settings){
				logger('something went wrong with payment settings data -> '.json_encode($payment_settings));
				throw new Exception("something went wrong");
			}

			$payment_settings = json_decode($payment_settings['settings_json'], true);
			$payment_settings['currency'] = $invoice->currency->code;
			$payment_settings['amount'] = $invoice->balance_due;
			$payment_settings['secret'] = decrypt($payment_settings['secret']);
			$payment_settings['invoice_id'] = $invoice->id;
			$payment_settings['company_id'] = $invoice->company_id;

			$payment_gateway_url = $this->generateGatewayUrl((int) $invoice->payment_gateway, $payment_settings);

			if(!$payment_gateway_url){
				//send an email to admins to notify the failure.
				$this->sendUrlGenerationFailedEmail((int) $invoice->payment_gateway);
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
		return $this->payment_repository->fetchInvoiceByUuid($uuid);
	}

	/**
	 * fetchExistingPaymentUrl function
	 *
	 * @param integer $invoice_id
	 * @return string|null
	 */
	public function fetchExistingPaymentUrl(int $invoice_id) : ?string {

		$transaction = $this->payment_repository->fetchTransactionOfPast($invoice_id);
		
		if(!$transaction){
			return null;
		}

		$invoice = $this->payment_repository->fetchInvoiceById($invoice_id);
		
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

}