<?php

namespace App\Modules\Payment;

use App\Models\Invoice;
use App\Models\PaymentUrl;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceUrl;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Models\TransactionReference;
use App\Modules\Payment\Enums\PayPalEvents;
use App\Modules\Payment\Enums\TransactionStatus;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Modules\Payment\Traits\UpdateInvoiceForTransaction;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use Illuminate\Support\Facades\Log;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
			PayPalEvents::CHECKOUT_APPROVED->value  => $data['resource']['id'],
			PayPalEvents::PAYMENT_CAPTURED->value   => $data['resource']['supplementary_data']['related_ids']['order_id'],
			PayPalEvents::PAYMENT_PENDING->value   	=> $data['resource']['supplementary_data']['related_ids']['order_id'],
			default                     			=> null
		};

	}


	/**
	 * fetchRequiredDataForWebhook function
	 *
	 * @param string $order_id
	 * @return Transaction|null
	 */
	private function fetchRequiredDataForWebhook(string $order_id) : ?Transaction {
		
		return Transaction::join('transaction_references', 'transaction_references.transaction_id', '=', 'transactions.id')
							->join('invoices', 'invoices.id', '=', 'transaction_references.invoice_id')
							->join('clients', 'clients.id', '=', 'invoices.client_id')
							->join('currencies', 'currencies.id', '=', 'invoices.currency_id')
							->where('transactions.token_id_identifier', '=', $order_id)
							->select(
								'transactions.currency_id as currency_id',
								'currencies.code as currency_code',
								'invoices.company_id as company_id',
								'invoices.id as invoice_id',
								'invoices.balance_due as balance_due',
								'clients.id as user_id'
							)->first();

	}

	private function fetchRequiredDataForWebhookPaymentRequest(string $order_id) : ?Transaction {
		
		return Transaction::join('transaction_references', 'transaction_references.transaction_id', '=', 'transactions.id')
							->join('payment_requests', 'payment_requests.id', '=', 'transaction_references.payment_request_id')
							->join('clients', 'clients.id', '=', 'payment_requests.client_id')
							->join('currencies', 'currencies.id', '=', 'payment_requests.currency_id')
							->where('transactions.token_id_identifier', '=', $order_id)
							->select(
								'transactions.currency_id as currency_id',
								'currencies.code as currency_code',
								'payment_requests.company_id as company_id',
								'payment_requests.id as payment_request_id',
								'payment_requests.amount as balance_due',
								'clients.id as user_id'
							)->first();

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

		$transaction = $this->fetchTransactionByTokenId($order_id);
		if($transaction->reference->invoice_id){
			$webhook_data = $this->fetchRequiredDataForWebhook($order_id);
		}else{
			//null invoice id means it is a payment request and not attached with any invoices.
			$webhook_data = $this->fetchRequiredDataForWebhookPaymentRequest($order_id);
		}
		
		if(!$webhook_data){
			throw new PaymentException('Invalid data provided', 'invalid_data', config('global.error_code'));
		}

		$webhook_data = $webhook_data->toArray();
		if($transaction->reference->invoice_id){
			$webhook_data['payment_request_id']	= null;
		}else{
			$webhook_data['invoice_id']	= null;
		}
		

		//$settings = $this->settings_section_repository->fetchSettings((int) $webhook_data['company_id'], PAYMENTS_PAYPAL_TYPE, true);
		
		return [
			'order_id'				=>	$order_id,
			'event_type'			=>	$event_type,
			'webhook_data'			=>	$webhook_data,
			//'settings'				=>	$settings,
			'is_invoice'			=>	($transaction->reference->invoice_id) ? true : false
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

		// if($event_type !== ''){

		// }
		
		if(!isset($data['data']['object']['metadata']['order_id'])){
			throw new PaymentException('Invalid data provided', 'unsupported_event', config('global.error_code'));
		}
		
		$order_id = $data['data']['object']['metadata']['order_id'];

		if(!$order_id){
			throw new PaymentException('Invalid data provided', 'invalid_order_id', config('global.error_code'));
		}

		$transaction = $this->fetchTransactionByTokenId($order_id);
		if($transaction->reference->invoice_id){
			$webhook_data = $this->fetchRequiredDataForWebhook($order_id);
		}else{
			//null invoice id means it is a payment request and not attached with any invoices.
			$webhook_data = $this->fetchRequiredDataForWebhookPaymentRequest($order_id);
		}

		if(!$webhook_data){
			throw new PaymentException('Invalid data provided', 'invalid_data', config('global.error_code'));
		}

		$webhook_data = $webhook_data->toArray();
		if($transaction->reference->invoice_id){
			$webhook_data['payment_request_id']	= null;
		}else{
			$webhook_data['invoice_id']	= null;
		}

		//$settings = $this->settings_section_repository->fetchSettings($webhook_data['company_id'], PAYMENTS_STRIPE_TYPE, true);

		return [
			'order_id'				=>	$order_id,
			'event_type'			=>	$event_type,
			'webhook_data'			=>	$webhook_data,
			//'settings'				=>	$settings
		];

	}

	/**
	 * fetchTransactionByToken function
	 *
	 * @param string $order_id
	 * @return Transaction|null
	 */
	public function fetchTransactionByToken(string $order_id) : ?Transaction {
		return Transaction::where('token_id_identifier', '=', $order_id)->with('reference')->first();
	}

	/**
	 * updatePayPalOrderApproved function
	 *
	 * @param Transaction $transaction
	 * @param array $data
	 * @return boolean
	 */
	public function updatePayPalOrderApproved(Transaction $transaction, array $data) : bool {

		$transaction->is_approved = 1;
		$saved = $transaction->save();

		TransactionGatewayDetail::updateOrCreate(
			['transaction_id' => $transaction->id],
			['payment_approved_details' => json_encode($data)]
		);

		return $saved;
	}

	/**
	 * updatePayPalPaymentCaputred function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function updatePayPalPaymentCaputred(Transaction $transaction, array $data) : bool {
		
		$breakdown = $data['resource']['seller_receivable_breakdown'];

		$gateway_fee = $breakdown['paypal_fee']['value'];         		// 0.94  - PayPal's cut
		$net_amount = $breakdown['net_amount']['value'];         		// 14.92 - what you actually received
		
		$transaction->gateway_fees_amount = $gateway_fee;
		$transaction->received_amount = $net_amount;

		$transaction->is_payment_captured = 1;
		$transaction->status = (int) TransactionStatus::COMPLETED->value;
		$transaction->paid_at = now();
		
		$saved = $transaction->save();

		DB::transaction(function () use ($transaction, $data) {
			
			TransactionGatewayDetail::updateOrCreate(
				['transaction_id' => $transaction->id],
				['payment_captured_details' => json_encode($data)]
			);
			
			$this->updateAndApplyPayment($transaction);
			
			if($transaction->reference->invoice_id){
				$this->updateInvoiceSnapshot((int) $transaction->reference->invoice_id);
			}
		});

		return $saved;

	}

	/**
	 * updatePayPalPending function
	 *
	 * @param Transaction $transaction
	 * @param array $data
	 * @return boolean
	 */
	public function updatePayPalPending(Transaction $transaction, array $data) : bool {

		$transaction->is_echeck = ($data['resource']['status_details']['reason'] === 'ECHECK') ? 1 : 0;
				
		$transaction->status = (int) TransactionStatus::PENDING->value;

		$saved = $transaction->save();

		TransactionGatewayDetail::updateOrCreate(
			['transaction_id' => $transaction->id],
			['echeck_pending_details' => ($data['resource']['status_details']['reason'] === 'ECHECK') ? json_encode($data) : null]
		);

		return $saved;

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
	 * insertEmptyTransactionGatewayDetails function
	 *
	 * @param integer $transaction_id
	 * @return TransactionGatewayDetail
	 */
	public function insertEmptyTransactionGatewayDetails(int $transaction_id) : TransactionGatewayDetail {
		$transaction_details = new TransactionGatewayDetail();
		$transaction_details->transaction_id = $transaction_id;
		$transaction_details->save();
		return $transaction_details;
	}

	/**
	 * updateStripePaymentTransaction function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function updateStripePaymentTransaction(array $data) : bool {
		
		$order_id = $data['order_id'];
		
		$transaction = Transaction::where('token_id_identifier', '=', $order_id)->with('reference')->first();
		
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
		
		$saved = $transaction->save() && $this->updateAndApplyPayment($transaction);
		
		if($transaction->reference->invoice_id){
			$this->updateInvoiceSnapshot((int) $transaction->reference->invoice_id);
		}
		

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
	 * @param integer|null $invoice_id
	 * @param string $gateway_url_identifier
	 * @param string $url_string
	 * @return boolean
	 */
	public function insertPaymentUrl(int $transaction_id, ?int $invoice_id, string $gateway_url_identifier, string $url_string) : bool {
		
		$payment_url = new PaymentUrl();
		$payment_url->invoice_id = $invoice_id;
		$payment_url->transaction_id = $transaction_id;
		$payment_url->gateway_url_identifier = $gateway_url_identifier;
		$payment_url->url = $url_string;
		return $payment_url->save();

	}

	/**
	 * upsertTransactionReference function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param integer|null $invoice_id
	 * @param integer|null $pr_id
	 * @param integer|null $transaction_id
	 * @param integer|null $id
	 * @return TransactionReference
	 */
	public function upsertTransactionReference(int $company_id, int $client_id, ?int $invoice_id, ?int $pr_id, ?int $transaction_id = null, ?int $id = null) : TransactionReference {

		if(!$id){
			$reference = new TransactionReference();
		}else{
			$reference = $this->getTransactionRefById($id);
		}

		$reference->company_id = $company_id;
		$reference->client_id = $client_id;
		$reference->invoice_id = $invoice_id;
		$reference->payment_request_id = $pr_id;
		$reference->transaction_id = $transaction_id;
		$reference->save();
		
		return $reference;

	}

	/**
	 * getTransactionRefById function
	 *
	 * @param integer $id
	 * @return TransactionReference|null
	 */
	public function getTransactionRefById(int $id) : ?TransactionReference {
		return TransactionReference::where('id', '=', $id)->first();
	}

	/**
	 * insertSubScriptionPlan function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function insertSubScriptionPlan(array $data) : bool {

		$plan = new SubscriptionPlan();
		$plan->uuid = Str::uuid()->toString();
		$plan->company_id = $data['company_id'];
		$plan->recurring_invoice_id = $data['recurring_invoice_id'];
		$plan->payment_gateway = $data['payment_gateway'];
		$plan->gateway_subscription_id = $data['gateway_subscription_id'];
		$plan->plan_id = $data['plan_id'];
		$plan->payer_id = $data['payer_id'];
		$plan->customer_id = $data['customer_id'];
		return $plan->save();

	}

	/**
	 * insertRecurringInvoiceUrl function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function insertRecurringInvoiceUrl(array $data) : bool {

		$url = new RecurringInvoiceUrl();
		
		$url->company_id = $data['company_id'];
		$url->recurring_invoice_id = $data['recurring_invoice_id'];
		$url->payment_gateway = $data['payment_gateway'];
		$url->total = $data['total'];
		$url->currency_id = $data['currency_id'];
		$url->url = $data['url'];
		
		return $url->save();

	}

	/**
	 * fetchSubscriptionPlanByRI function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @param integer $payment_gateway
	 * @return SubscriptionPlan|null
	 */
	public function fetchSubscriptionPlanByRI(int $company_id, int $recurring_invoice_id, int $payment_gateway) : ?SubscriptionPlan {
		return SubscriptionPlan::where([['company_id', '=', $company_id], ['recurring_invoice_id', '=', $recurring_invoice_id], ['payment_gateway', '=', $payment_gateway]])->orderBy('id', 'desc')->first();
	}

	/**
	 * fetchRecurringInvoice function
	 *
	 * @param integer $id
	 * @param array $selects
	 * @return RecurringInvoice|null
	 */
	public function fetchRecurringInvoice(int $id, array $selects = ['*']) : ?RecurringInvoice {
		return RecurringInvoice::select(...$selects)->join('currencies', 'currencies.id', '=', 'recurring_invoices.currency_id')->where('recurring_invoices.id', '=', $id)->first();
	}

	/**
	 * getPayPalCustomId function
	 *
	 * @param array $request_data
	 * @return integer|null
	 */
	public function getPayPalCustomId(array $request_data) : ?int {

		$event_type = $request_data['event_type'] ?? null;

		if((string) $event_type === PayPalEvents::SALE_COMPLETED->value){
			if(isset($request_data['resource']['custom'])){
				return (int) $request_data['resource']['custom'];
			}
		}

		if((string) $event_type === PayPalEvents::SUBSCRIPTION_ACTIVATED->value){
			if(isset($request_data['resource']['custom_id'])){
				return (int) $request_data['resource']['custom_id'];
			}
		}

		return null;

	}

	/**
	 * fetchPayPalSettingsForSubscriptions function
	 *
	 * @param array $request_data
	 * @return array|null
	 */
	public function fetchPayPalSettingsForSubscriptions(array $request_data) : ?array {
		
		$recurring_invoice_id = $this->getPayPalCustomId($request_data);

		if(!$recurring_invoice_id){
			return null;
		}

		$recurring_invoice_data = $this->fetchRecurringInvoice((int) $recurring_invoice_id, ['recurring_invoices.*', 'currencies.code as currency_code'])->toArray();

		$settings = $this->settings_section_repository->fetchSettings((int) $recurring_invoice_data['company_id'], PAYMENTS_PAYPAL_TYPE, true);

		return [
			'recurring_invoice_data'	=>	$recurring_invoice_data,
			'settings'					=>	$settings,
		];

	}

	/**
	 * createTransactionForRecurringSale function
	 *
	 * @param array $data
	 * @return Transaction
	 */
	public function createTransactionForRecurringSale(array $data) : Transaction {

		$transaction = new Transaction();

		$transaction->forceFill($data)->save();

		return $transaction;

	}

	/**
	 * upsertTransactionGatewayDetails function
	 *
	 * @param integer $transaction_id
	 * @param string $key
	 * @param array $data
	 * @return void
	 */
	public function upsertTransactionGatewayDetails(int $transaction_id, string $key, array $data) : void {

		TransactionGatewayDetail::updateOrCreate(
			['transaction_id' => $transaction_id],
			[$key => json_encode($data)]

		);
	}

	/**
	 * fetchCompletedTransaction function
	 *
	 * @param integer $company_id
	 * @param string $order_id
	 * @return Transaction|null
	 */
	public function fetchCompletedTransaction(int $company_id, string $order_id) : ?Transaction {
		return Transaction::where([['company_id', '=', $company_id], ['token_id_identifier', '=', $order_id], ['status', '=', TransactionStatus::COMPLETED->value]])->first();
	}

}