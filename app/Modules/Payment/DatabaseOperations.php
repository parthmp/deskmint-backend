<?php

namespace App\Modules\Payment;

use App\Models\Invoice;
use App\Models\PaymentUrl;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Traits\UpdateInvoiceForTransaction;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Illuminate\Support\Facades\Log;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class DatabaseOperations{

	use UpdateInvoiceForTransaction;

	//private SettingsSectionRepository $settings_section_repository;

	public function __construct(private SettingsSectionRepository $settings_section_repository)
	{
		//$this->settings_section_repository = new SettingsSectionRepository();
	}
	
	/**
	 * createEmptyTransaction function
	 *
	 * @return Transaction
	 */
	public function createEmptyTransaction() : Transaction {
		return new Transaction();
	}

	/**
	 * insertTransaction function
	 *
	 * @param array $data
	 * @return Transaction
	 */
	public function insertTransaction(array $data) : Transaction {
		$transaction = $this->createEmptyTransaction();
		$transaction->currency_id = $data['currency_id'];
		$transaction->company_id = $data['company_id'];
		$transaction->amount = $data['amount'];
		$transaction->payment_gateway = $data['payment_gateway'];
		$transaction->mode = $data['mode'];
		$transaction->token_id_identifier = $data['token_id_identifier'];
		$transaction->is_approved = $data['is_approved'];
		$transaction->is_payment_captured = $data['is_payment_captured'];
		$transaction->status = $data['status'];
		$transaction->save();
		return $transaction;
	}

	/**
	 * findOrderId function
	 *
	 * @param array $data
	 * @param string|null $event_type
	 * @return string|null
	 */
	public function findOrderId(array $data, ?string $event_type) : ?string {

		if(!$event_type){
			throw new PaymentException('Invalid data provided', 'invalid_event_type', config('global.error_code'));
		}
		
		return match($event_type) {
			'CHECKOUT.ORDER.APPROVED'   => $data['resource']['id'],
			'PAYMENT.CAPTURE.COMPLETED' => $data['resource']['supplementary_data']['related_ids']['order_id'],
			'PAYMENT.CAPTURE.PENDING' 	=> $data['resource']['supplementary_data']['related_ids']['order_id'],
			default                     => null
		};

	}

	/**
	 * fetchRequiredDataForWebhook function
	 *
	 * @param string $order_id
	 * @return Transaction|null
	 */
	private function fetchRequiredDataForWebhook(string $order_id) : ?Transaction {
		
		return Transaction::join('invoices', 'invoices.id', '=', 'transactions.invoice_id')
				->join('clients', 'clients.id', '=', 'invoices.client_id')
				->join('currencies', 'currencies.id', '=', 'invoices.currency_id')
				->where('transactions.token_id_identifier', '=', $order_id)
				->select(
						'currencies.code as currency_code',
						'invoices.company_id as company_id',
						'invoices.id as invoice_id',
						'invoices.balance_due as balance_due'
					)
			->first();
	}

	/**
	 * fetchPayPalSettings function
	 *
	 * @param array $data
	 * @return array
	 */
	public function fetchPayPalSettings(array $data) : array {

		$event_type = $data['event_type'] ?? null;
		
		$order_id = $this->findOrderId($data, $event_type);
		
		if(!$order_id){
			throw new PaymentException('Invalid data provided', 'invalid_order_id', config('global.error_code'));
		}

		$webhook_data = $this->fetchRequiredDataForWebhook($order_id);
		
		if(!$webhook_data){
			throw new PaymentException('Invalid data provided', 'invalid_data', config('global.error_code'));
		}

		$settings = $this->settings_section_repository->fetchSettings($webhook_data->company_id, PAYMENTS_PAYPAL_TYPE, true);

		return [
			'order_id'				=>	$order_id,
			'event_type'			=>	$event_type,
			'webhook_data'			=>	$webhook_data,
			'settings'				=>	$settings
		];

	}

	/**
	 * fetchStripeSettings function
	 *
	 * @param array $data
	 * @return array
	 */
	public function fetchStripeSettings(array $data) : array {

		$event_type = $data['type'] ?? null;

		if($event_type !== ''){

		}

		if(!isset($data['data']['object']['metadata']['payment_id'])){
			throw new PaymentException('Invalid data provided', 'unsupported_event', config('global.error_code'));
		}
		
		$order_id = $data['data']['object']['metadata']['payment_id'];

		if(!$order_id){
			throw new PaymentException('Invalid data provided', 'invalid_order_id', config('global.error_code'));
		}

		$webhook_data = $this->fetchRequiredDataForWebhook($order_id);

		if(!$webhook_data){
			throw new PaymentException('Invalid data provided', 'invalid_data', config('global.error_code'));
		}

		$settings = $this->settings_section_repository->fetchSettings($webhook_data->company_id, PAYMENTS_STRIPE_TYPE, true);

		return [
			'order_id'				=>	$order_id,
			'event_type'			=>	$event_type,
			'webhook_data'			=>	$webhook_data,
			'settings'				=>	$settings
		];

	}

	/**
	 * updatePaymentTransaction function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function updatePaymentTransaction(array $data) : bool {
		
		try {
			
			$event_type = $data['event_type'] ?? null;

			$order_id = $this->findOrderId($data, $event_type);

			$transaction = Transaction::where('token_id_identifier', '=', $order_id)->first();
			
			if(!$transaction){
				Log::error('Payment update failed $transaction was null');
				throw new PaymentException('failed to update database', 'db_failed', config('global.error_code'));
			}
			
			$saved = false;

			if($event_type === 'CHECKOUT.ORDER.APPROVED'){
				
				$transaction->is_approved = 1;
				$saved = $transaction->save();

				TransactionGatewayDetail::updateOrCreate(
					['transaction_id' => $transaction->id],
					['payment_approved_details' => json_encode($data)]
				);


			}else if($event_type === 'PAYMENT.CAPTURE.COMPLETED'){

				
				$breakdown = $data['resource']['seller_receivable_breakdown'];

				$gateway_fee  = $breakdown['paypal_fee']['value'];         		// 0.94  - PayPal's cut
				$net_amount   = $breakdown['net_amount']['value'];         		// 14.92 - what you actually received
				
				$transaction->gateway_fees_amount = $gateway_fee;
				$transaction->received_amount = $net_amount;

				$transaction->is_payment_captured = 1;
				$transaction->status = (int) TransactionStatus::COMPLETED->value;
				$transaction->paid_at = now();
				
				$saved = $transaction->save();
				
				TransactionGatewayDetail::updateOrCreate(
					['transaction_id' => $transaction->id],
					['payment_captured_details' => json_encode($data)]
				);
				
				$this->updateInvoiceStatusForPayments($transaction);
				
				$this->updateInvoiceSnapshot((int) $transaction->invoice_id);

				

			}else if($event_type === 'PAYMENT.CAPTURE.PENDING'){

				$transaction->is_echeck = ($data['resource']['status_details']['reason'] === 'ECHECK') ? 1 : 0;
				
				$transaction->status = (int) TransactionStatus::PENDING->value;

				$saved = $transaction->save();

				TransactionGatewayDetail::updateOrCreate(
					['transaction_id' => $transaction->id],
					['echeck_pending_details' => ($data['resource']['status_details']['reason'] === 'ECHECK') ? json_encode($data) : null]
				);

			}

			return $saved;

		}catch(\Exception $e){

			Log::error('Payment update failed', ['error' => $e->getMessage()]);

			return false;

		}

	}

	/**
	 * fetchTransactionByTokenId function
	 *
	 * @param string $identifer
	 * @return Transaction
	 */
	public function fetchTransactionByTokenId(string $identifer) : Transaction {
		return Transaction::where('token_id_identifier', '=', $identifer)->first();
	}

	/**
	 * updateStripePaymentTransaction function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function updateStripePaymentTransaction(array $data) : bool {

		$order_id = $data['order_id'];

		$transaction = Transaction::where('token_id_identifier', '=', $order_id)->first();

		if(!$transaction){
			Log::error('Payment update failed $transaction was null');
			throw new PaymentException('failed to update database', 'db_failed', config('global.error_code'));
		}
		
		$transaction->is_approved = 1;
		$transaction->is_payment_captured = 1;
		$transaction->status = (int) TransactionStatus::COMPLETED->value;
		//$transaction->payment_captured_details = json_encode($data);
		$transaction->gateway_fees_amount = $data['gateway_fees_amount'];
		$transaction->received_amount = $data['received_amount'];
		$transaction->paid_at = now();
		$transaction->save();

		TransactionGatewayDetail::updateOrCreate(
			['transaction_id' => $transaction->id],
			['payment_captured_details' => json_encode($data)]
		);
		
		$amount = (int) $data['data']['object']['amount_total'];
		$amount = BigDecimal::of($amount)->dividedBy(100, 2, RoundingMode::HalfUp)->toFloat();

		$saved = $transaction->save() && $this->updateInvoiceStatusForPayments($transaction);

		$this->updateInvoiceSnapshot((int) $transaction->invoice_id);

		return $saved;

	}

	/**
	 * fetchInvoiceById function
	 *
	 * @param integer $invoice_id
	 * @return ?Invoice
	 */
	public function fetchInvoiceById(int $invoice_id) : ?Invoice {
		return Invoice::where('id', '=', $invoice_id)->first();
	}

	/**
	 * insertPaymentUrl function
	 *
	 * @param integer $transaction_id
	 * @param string $gateway_url_identifier
	 * @param string $url_string
	 * @return boolean
	 */
	public function insertPaymentUrl(int $transaction_id, int $invoice_id, string $gateway_url_identifier, string $url_string) : bool {
		
		$payment_url = new PaymentUrl();
		$payment_url->invoice_id = $invoice_id;
		$payment_url->transaction_id = $transaction_id;
		$payment_url->gateway_url_identifier = $gateway_url_identifier;
		$payment_url->url = $url_string;
		return $payment_url->save();

	}

}