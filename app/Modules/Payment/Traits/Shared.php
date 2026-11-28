<?php

namespace App\Modules\Payment\Traits;

use App\Models\Payment;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Notification;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Services\Invoice\InvoiceRefundService;
use Brick\Math\BigDecimal;

trait Shared {

	/**
	 * getInvoiceRefundService function
	 *
	 * @return InvoiceRefundService
	 */
	private function getInvoiceRefundService() : InvoiceRefundService {
		return app(InvoiceRefundService::class);
	}
	
	/**
	 * sendPaymentFailedAdminEmail function
	 *
	 * @param string $subscription_id
	 * @param array $settings_data
	 * @param string $gateway_name
	 * @return void
	 */
	public function sendPaymentFailedAdminEmail(string $subscription_id, array $settings_data, string $gateway_name) : void {

		$recurring_invoice_data = $settings_data['webhook_data'];

		$message = 'Hello, '.$gateway_name.' could not complete a payment for subscription (ID: '.$subscription_id.'). Please contact the customer to resolve the issue.';
		$message .= "\n";
		$message .= "\nDetails are,\n";
		$message .= "System recurring invoice id: ".$recurring_invoice_data['id'];
		$message .= "\n";
		$message .= "Client: ".$recurring_invoice_data['first_name'].' '.$recurring_invoice_data['last_name'];

		$notification = app(Notification::class);
		$notification->notify((int) $recurring_invoice_data['company_id'], NotificationType::SUBSCRIPTION_PAYMENT_FAILED, 'Failed to charge subscription', $message, []);

	}

	/**
	 * handleRefunds function
	 *
	 * @param array $request_data
	 * @param array $settings_data
	 * @param integer $payment_gateway
	 * @return void
	 */
	public function handleRefunds(array $request_data, array $settings_data, int $payment_gateway) : void {

		$transaction = $settings_data['transaction'];
		
		if($transaction === null){
			return ;
		}
		
		if($payment_gateway === PaymentGateway::PAYPAL->value){
			
			$obj = $request_data['resource'];

			$ignore_invoice_id = $obj['invoice_id'] ?? '';

			if(str_contains($ignore_invoice_id, config('global.ignore_paypal_refund_webhook'))){
				return ;
			}

		}else if($payment_gateway === PaymentGateway::STRIPE->value){

			$obj = $request_data['data']['object'];

			$ledger_id = $obj['metadata']['ledger_id'] ?? null;

			if($ledger_id !== null){
				return ;
			}

		}
		
		
		$refund_id = $obj['id'] ?? null;
		
		if($refund_id === null){
			return ;
		}
		
		$refund_log = $this->db->fetchRefundLogByGatewayId($refund_id, ['id']);
		
		if($refund_log !== null){
			return ;
		}

		$status = $obj['status'] ?? null;

		$payment = $this->db->fetchPaymentByTransactionId((int) $transaction['company_id'], (int) $transaction['id']);

		if($payment === null){
			return ;
		}

		$ledger_entries = $this->db->getInvoiceLedgerEntriesForPayment((int) $payment->id);
		
		if((int) $ledger_entries->count() === 0 || (int) $ledger_entries->count() > 1){
			/**
			 * > 1 means user tried to refund from payment gateway dashbord and system does not know which invoice to log refund to.
			 */
			return ;
		}

		
		$status = $obj['status'] ?? null;

		$comp_status = false;
		$refunded_amount = 0;

		if($payment_gateway === PaymentGateway::STRIPE->value){
			$comp_status = strtolower($status) === 'succeeded';
			$cents = $obj['amount'] ?? null;
			$refunded_amount = BigDecimal::of($cents)->withPointMovedLeft(2);
		}else if($payment_gateway === PaymentGateway::PAYPAL->value){
			$comp_status = strtoupper($status) === 'COMPLETED';
			$refunded_amount = $obj['amount']['value'] ?? null;
		}

		if(!$refunded_amount){
			return ;
		}

		if($comp_status){
			
			$ledger_entry = $ledger_entries[0];

			$invoice_refund_service = $this->getInvoiceRefundService();

			$ledger_rows = $invoice_refund_service->assembleLedgerRows((int) $transaction['company_id'], $ledger_entries, 'payment');

			$online_payments[] = [
				'text' 			=> '-',
				'value' 		=> $ledger_entry['id'],
				'meta'			=>	[
					'id'				=>	$ledger_entry['id'],
					'amount'			=>	$ledger_rows[0]['amount'],
					'filled_amount'		=>	$refunded_amount,
					'type'				=>	'Payment',
					'payment_gateway'	=>	PaymentGateway::getLabelByValue($payment_gateway),
				]
			];
			
			$invoice_refund_service->issueRefunds((int) $transaction['company_id'], (int) $ledger_entry['invoice_id'], false, $online_payments, ['gateway_refund_id' => $refund_id]);

		}


	}

}