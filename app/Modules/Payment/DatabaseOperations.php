<?php

namespace App\Modules\Payment;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Invoice;
use App\Models\InvoiceSnapshot;
use App\Models\PaymentUrl;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Modules\InvoiceGeneration\InvoiceSnapshot as Snapshot;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;

class DatabaseOperations{

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
		$transaction->invoice_id = $data['invoice_id'];
		$transaction->amount = $data['amount'];
		$transaction->payment_method = $data['payment_method'];
		$transaction->mode = $data['mode'];
		$transaction->token_id_identifier = $data['token_id_identifier'];
		$transaction->is_approved = $data['is_approved'];
		$transaction->is_payment_captured = $data['is_payment_captured'];
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
				->join('currencies', 'currencies.id', '=', 'clients.currency_id')
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
	 * markInvoicePaid function
	 *
	 * @param integer $invoice_id
	 * @return boolean
	 */
	private function markInvoicePaidnDeduct(int $invoice_id, float $amount) : bool {
		
		$invoice = Invoice::where('id', $invoice_id)->first();

		$amount_paid = BigDecimal::of($amount);
		$amount_balance_due = BigDecimal::of($invoice->balance_due);

		$left = $amount_balance_due->minus($amount_paid);

		$paid_in_full = ($amount_paid->isEqualTo($amount_balance_due) || $amount_paid->isGreaterThan($amount_balance_due)) ? 1 : 0;

		$left = $left->toScale(2, RoundingMode::HalfUp)->__toString();
		

		$invoice->status = ((int) $paid_in_full === 1) ? (int) InvoiceStatus::PAID->value : (int) InvoiceStatus::PARTIALLY_PAID->value;
		$invoice->balance_due = $left;

		return $invoice->save();

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
				$transaction->paid_at = now();
				
				$saved = $transaction->save();

				TransactionGatewayDetail::updateOrCreate(
					['transaction_id' => $transaction->id],
					['payment_captured_details' => json_encode($data)]
				);
				
				$this->markInvoicePaidnDeduct((int) $transaction->invoice_id, (float) $data['resource']['amount']['value']);

				$this->updateInvoiceSnapshot((int) $transaction->invoice_id);

				

			}else if($event_type === 'PAYMENT.CAPTURE.PENDING'){

				$transaction->is_echeck = ($data['resource']['status_details']['reason'] === 'ECHECK') ? 1 : 0;
				
				$transaction->is_pending = 1;

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

		$saved = $transaction->save() && $this->markInvoicePaidnDeduct((int) $transaction->invoice_id, $amount);

		$this->updateInvoiceSnapshot((int) $transaction->invoice_id);

		return $saved;

	}
	
	/**
	 * updateInvoiceSnapshot function
	 *
	 * @param integer $invoice_id
	 * @return void
	 */
	private function updateInvoiceSnapshot(int $invoice_id) : void {
		
		$invoice = $this->fetchInvoiceById($invoice_id);

		if($invoice !== null){
			$snapshot = app(Snapshot::class)
						->setCompanyId($invoice->company_id)
						->setInvoiceId($invoice->id)
						->setTimezoneOffset($invoice->timezone_offset_minutes)
						->setLogoSnapsot()
						->setGeneralSettings()
						->setClientSnapshot()
						->setCompanySnapshot()
						->setInvoiceSnapshot()
						->setInvoiceRowsSnapshot()
						->setTotalsSnapshot()
						->setTermsSnapshot()
						->output();

				InvoiceSnapshot::updateOrCreate(
					['invoice_id' 	=> $invoice->id],
					['snapshot' 	=> $snapshot]
				);

			//regenerate pdf
			GenerateInvoiceJob::dispatch($invoice->company_id, $invoice->id);
		}
		
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
	public function insertPaymentUrl(int $transaction_id, string $gateway_url_identifier, string $url_string) : bool {
		
		$payment_url = new PaymentUrl();
		$payment_url->transaction_id = $transaction_id;
		$payment_url->gateway_url_identifier = $gateway_url_identifier;
		$payment_url->url = $url_string;
		return $payment_url->save();

	}

}