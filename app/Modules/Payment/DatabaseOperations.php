<?php

namespace App\Modules\Payment;

use App\Models\Transaction;
use App\Modules\Payment\Exceptions\PaymentException;
use App\Repositories\SettingsSection\SettingsSectionRepository;

class DatabaseOperations{

	private SettingsSectionRepository $settings_section_repository;

	public function __construct()
	{
		$this->settings_section_repository = new SettingsSectionRepository();
	}
	
	/**
	 * createEmptyTransaction function
	 *
	 * @return Transaction
	 */
	public function createEmptyTransaction() : Transaction{
		return new Transaction();
	}

	/**
	 * insertTransaction function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function insertTransaction(array $data) : bool {
		$transaction = $this->createEmptyTransaction();
		$transaction->invoice_id = $data['invoice_id'];
		$transaction->amount = $data['amount'];
		$transaction->payment_method = $data['payment_method'];
		$transaction->mode = $data['mode'];
		$transaction->token_id_identifier = $data['token_id_identifier'];
		$transaction->payment_approved_details = $data['payment_approved_details'];
		$transaction->payment_captured_details = $data['payment_captured_details'];
		$transaction->is_approved = $data['is_approved'];
		$transaction->is_payment_captured = $data['is_payment_captured'];
		return $transaction->save();
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
						'invoices.total as total'
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
	 * updatePaymentTransaction function
	 *
	 * @param array $data
	 * @return boolean
	 */
	public function updatePaymentTransaction(array $data) : bool {
		
		$event_type = $data['event_type'] ?? null;

		$order_id = $this->findOrderId($data, $event_type);

		$transaction = Transaction::where('token_id_identifier', '=', $order_id);

		$affected = 0;

		if($event_type === 'CHECKOUT.ORDER.APPROVED'){
			
			$affected = $transaction->update([
				'is_approved'				=>	1,
				'payment_approved_details'	=>	json_encode($data)
			]);

		}else if($event_type === 'PAYMENT.CAPTURE.COMPLETED'){

			$affected = $transaction->update([
				'is_payment_captured'		=>	1,
				'payment_captured_details'	=>	json_encode($data)
			]);

		}

		return $affected > 0;

	}

}