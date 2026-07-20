<?php

namespace App\Repositories\Transaction;

use App\Helpers\General;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\TransactionGatewayDetail;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\TransactionStatus;
use Illuminate\Support\Str;

/**
 * TransactionRepository class
 */
class TransactionRepository {

	/**
	 * searchInvoicesByInvoiceNumber function
	 *
	 * @param integer $company_id
	 * @param string $searched
	 * @return array
	 */
	public function searchInvoicesByInvoiceNumber(int $company_id, string $searched) : array {

		$escaped_search = str_replace(['%', '_'], ['\%', '\_'], $searched);

		$invoices = Invoice::select('invoices.id as id', 'invoices.invoice_number as invoice_number', 'currencies.code as currency_code')->join('currencies', 'currencies.id', '=', 'invoices.currency_id')->where('company_id', '=', $company_id)->where('invoice_number', 'LIKE', '%'.$escaped_search.'%')->orderBy('invoice_number', 'ASC')->limit(50)->get()->map(function($invoice){
			return [
				'text'		=>	$invoice->invoice_number.' ('.$invoice->currency_code.')',
				'value'		=>	$invoice->id
			];
		})->toArray();

		return $invoices;

	}

	/**
	 * createManualTransaction function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param float $amount
	 * @param float $gateway_fees
	 * @param float $received_amount
	 * @param integer $payment_method
	 * @return Transaction
	 */
	public function createManualTransaction(int $company_id, int $invoice_id, float $amount, float $gateway_fees, float $received_amount, int $payment_method) : Transaction {

		$transaction = new Transaction();
		$transaction->company_id = $company_id;
		$transaction->invoice_id = $invoice_id;
		$transaction->amount = $amount;
		$transaction->gateway_fees_amount = $gateway_fees;
		$transaction->received_amount = $received_amount;
		$transaction->payment_method = $payment_method;
		$transaction->mode = 'Manual';
		$transaction->token_id_identifier = Str::uuid();
		$transaction->is_approved = 1;
		$transaction->is_payment_captured = 1;
		$transaction->status = TransactionStatus::COMPLETED->value;
		$transaction->is_echeck = 0;
		$transaction->paid_at = now();
		$transaction->save();

		$tgd = new TransactionGatewayDetail();
		$tgd->transaction_id = $transaction->id;
		$tgd->payment_approved_details = '';
		$tgd->payment_captured_details = '';
		$tgd->echeck_pending_details = '';
		$tgd->save();

		return $transaction;

	}
	
	/**
	 * fetchInvoiceDataById function
	 *
	 * @param integer $invoice_id
	 * @return array|null
	 */
	public function fetchInvoiceDataById(int $invoice_id) : ?array {

		$invoice = Invoice::select('invoices.id as id', 'invoices.invoice_number as invoice_number', 'currencies.code as currency_code')->join('currencies', 'currencies.id', '=', 'invoices.currency_id')->where('invoices.id', '=', $invoice_id)->first();

		if(!$invoice){
			return null;
		}

		return [
			'invoice_id'	=> $invoice->id,
			'value'			=> $invoice->invoice_number.' ('.$invoice->currency_code.')',
			'error'			=> ''
		];

	}

	/**
	 * validateInvoiceForTransaction function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function validateInvoiceForTransaction(int $invoice_id) : array {

		$invoice = Invoice::where('id', '=', $invoice_id)->first();

		return [
			'exists'	=>	$invoice ? true : false,
			'cancelled'	=>	$invoice ? $invoice->status === InvoiceStatus::CANCELLED->value : false,
		];

	}

	/**
	 * fetchTransactionView function
	 *
	 * @param integer $transaction_id
	 * @return array
	 */
	public function fetchTransactionView(int $transaction_id, int $company_id) : array {

		$transaction = Transaction::select(
			'transactions.*',
			'currencies.code as currency_code',
			'invoices.invoice_number as invoice_number',
			'users.name as voided_by_name',
		)->where([['transactions.id', '=', $transaction_id], ['transactions.company_id', '=', $company_id]])->join('invoices', 'invoices.id', '=', 'transactions.invoice_id')->join('currencies', 'currencies.id', '=', 'invoices.currency_id')->leftjoin('users', 'users.id', '=', 'transactions.voided_by')->first();

		if(!$transaction){
			return [];
		}

		$transaction = $transaction->toArray();

		$transaction['payment_method'] = General::getPaymentMethodName((int) $transaction['payment_method']);
		$transaction['status'] = TransactionStatus::getTransactionStatusLabel((int) $transaction['status']);
		$transaction['is_approved'] = ((int) $transaction['is_approved'] === 1) ? 'Yes' : 'No';
		$transaction['is_payment_captured'] = ((int) $transaction['is_payment_captured'] === 1) ? 'Yes' : 'No';
		$transaction['is_echeck'] = ((int) $transaction['is_echeck'] === 1) ? 'Yes' : 'No';

		return $transaction;

	}

}