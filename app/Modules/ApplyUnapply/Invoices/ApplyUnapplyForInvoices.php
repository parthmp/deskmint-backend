<?php

namespace App\Modules\ApplyUnapply\Invoices;

use App\Enums\Credits\CreditStatus;
use App\Helpers\Sanitize;
use App\Jobs\GenerateInvoiceSnapshotJob;
use App\Modules\ApplyUnapply\Common\Traits\ApplyUnapplyCommon;
use App\Modules\ApplyUnapply\Invoices\DB\DB;
use App\Modules\ApplyUnapply\Invoices\Validation;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Services\Invoice\Exceptions\InvoiceException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as FacadesDB;

class ApplyUnapplyForInvoices {

	use ApplyUnapplyCommon;

	public function __construct(
		private DB $db,
		private Validation $validation
	){}

	/**
	 * fetchInvoiceForApplyUnapplyCredit function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchInvoiceForApplyUnapply(int $company_id, int $invoice_id) : array {

		$invoice = $this->db->fetchByIdWithComapanyId($company_id, $invoice_id);
		
		return [
			'id'				=>	$invoice->id,
			'amount'			=>	$invoice->total,
			'amount_left'		=>	$invoice->balance_due,
			'due'				=>	$invoice->balance_due,
			'invoice_number'	=>	$invoice->invoice_number,
			'currency_code'		=>	$invoice->currency_code,
			'full_name'			=>	$invoice->first_name.' '.$invoice->last_name,
		];

	}

	/**
	 * fetchAlreadyAppliedEntries function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param string $type
	 * @return array
	 */
	public function fetchAlreadyAppliedEntries(int $company_id, int $invoice_id, int $currency_id, int $client_id, string $type = 'credit') : array {
		return $this->db->fetchAlreadyAppliedEntries($company_id, $invoice_id, $currency_id, $client_id, $type);
	}

	/**
	 * fetchEntriesForApplyUnapply function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param integer $invoice_id
	 * @param string $searched
	 * @param array $locally_applied_ids
	 * @param array $fully_applied_ids
	 * @param string $type
	 * @return array
	 */
	public function fetchEntriesForApplyUnapply(int $company_id, int $currency_id, int $client_id, int $invoice_id, string $searched, array $locally_applied_ids, array $fully_applied_ids = [], $type = 'credit') : array {
		return $this->db->fetchEntriesForApplyUnapply($company_id, $currency_id, $client_id, $invoice_id, $searched, $locally_applied_ids, $fully_applied_ids, $type);
	}

	/**
	 * validateForInvoice function
	 *
	 * @param Request $request
	 * @param string $type
	 * @return boolean
	 */
	public function validateForInvoice(Request $request, string $type) : bool {
		return $this->validation->validateApplyUnapplyForInvoice($request, $type);
	}

	/**
	 * modifyLedger function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $applied_entries
	 * @param array $removed_credit_ids
	 * @param string $type
	 * @return void
	 */
	public function modifyLedger(int $company_id, int $invoice_id, array $applied_entries, array $removed_credit_ids, string $type) : void {

		//delete first
		$this->db->removeLedgerEntries($company_id, $invoice_id, $removed_credit_ids, $type);
		
		//now check which ones were modified. this is required because later on, we will use updated_at to track which credit or payment applied when.
		$update = [];
		$insert = [];

		$entries = $this->db->fetchLedgerEntriesForApply($company_id, $invoice_id, $type);

		foreach($applied_entries as $credit_or_payment){

			$credit_or_payment['id'] = Sanitize::input($credit_or_payment['id']);
			$credit_or_payment['amount'] = Sanitize::input($credit_or_payment['amount']);

			$found = false;
			
			foreach($entries as $entry){

				if((int) $credit_or_payment['id'] === (int) $entry[$type.'_id']){

					$found = true;

					$already_applied = BigDecimal::of($entry['applied_amount_from_'.$type.'s']);
					$to_be_applied = BigDecimal::of($credit_or_payment['amount']);

					//update only if amount changes.
					if(!$already_applied->isEqualTo($to_be_applied)){
						if($type === 'credit'){
							$update[] = [
								'id'							=>	$entry['id'],
								'applied_amount_from_credits'	=>	$credit_or_payment['amount'],
								'applied_amount_from_payments'	=>	0,
								'total_applied'					=>	$credit_or_payment['amount']
							];
						}else if($type === 'payment'){
							$update[] = [
								'id'							=>	$entry['id'],
								'applied_amount_from_credits'	=>	0,
								'applied_amount_from_payments'	=>	$credit_or_payment['amount'],
								'total_applied'					=>	$credit_or_payment['amount']
							];
						}
						
					}

				}

			}

			if(!$found){
				//this is where we add new entries.
				$temp_insert = [
					'company_id'						=>	$company_id,
					'invoice_id'						=>	$invoice_id,
					'total_applied'						=>	$credit_or_payment['amount'],
					'created_at'						=>	now(),
					'updated_at'						=>	now()
				];

				if($type === 'credit'){

					$temp_insert['payment_id'] = null;
					$temp_insert['credit_id'] = $credit_or_payment['id'];
					$temp_insert['applied_amount_from_payments'] = 0;
					$temp_insert['applied_amount_from_credits'] = $credit_or_payment['amount'];

				}else if($type === 'payment'){

					$temp_insert['payment_id'] = $credit_or_payment['id'];
					$temp_insert['credit_id'] = null;
					$temp_insert['applied_amount_from_payments'] = $credit_or_payment['amount'];
					$temp_insert['applied_amount_from_credits'] = 0;

				}

				$insert[] = $temp_insert;
			}

		}

		//now need chunks.
		if((int) count($insert) > 0){
			$insert = array_chunk($insert, 50);
			$this->db->insertNewLedgerEntries($insert);
		}
		
		if((int) count($update) > 0){
			$update = array_chunk($update, 50);
			$this->db->updateLedgerEntries($update);
		}
		

	}

	
	/**
	 * updateEntries function
	 *
	 * @param integer $company_id
	 * @param array $entry_ids
	 * @param string $type
	 * @return void
	 */
	public function updateEntries(int $company_id, array $entry_ids, string $type) : void {
		
		$ledger_entries = $this->db->fetchMultipleLedgerOfIds($company_id, $entry_ids, $type);
		$credits_or_payments = $this->db->fetchMultipleEntriesByIdsForApply($company_id, $entry_ids, $type);

		$not_applied_status = CreditStatus::NOT_APPLIED->value;
		$partially_applied_status = CreditStatus::PARTIALLY_APPLIED->value;
		$applied_status = CreditStatus::APPLIED->value;

		if($type === 'payment'){
			$not_applied_status = PaymentStatus::NOT_APPLIED->value;
			$partially_applied_status = PaymentStatus::PARTIALLY_APPLIED->value;
			$applied_status = PaymentStatus::APPLIED->value;
		}

		$update = [];
		foreach($credits_or_payments as $credit_or_payment){
			
			$sum = BigDecimal::of(0);
			$applied_amount_from_db = BigDecimal::of($credit_or_payment['applied_amount']);
			$status = $not_applied_status;
			$total = BigDecimal::of($credit_or_payment['amount']);

			foreach($ledger_entries as $entry){
				if((int) $entry[$type.'_id'] === (int) $credit_or_payment['id']){
					$sum = $sum->plus($entry['applied_amount_from_'.$type.'s']);
				}
			}

			if(!$sum->isEqualTo($applied_amount_from_db)){

				if($sum->isLessThan($total) && $sum->isGreaterThan(BigDecimal::of(0))){
					$status = $partially_applied_status;
				}else if($sum->isEqualTo($total)){
					$status = $applied_status;
				}

				$left = $total->minus($sum);

				if($left->isLessThan(BigDecimal::of(0))){
					throw new InvoiceException('Something went wrong in calculation', 'unexpected_error_calc', (int) config('global.error_code'));
				}

				$update[] = [
					'id' 						=> $credit_or_payment['id'],
					'status' 					=> $status,
					'applied_amount' 			=> $sum->toScale(2, RoundingMode::HalfUp)->__toString(),
					'amount_left_to_be_applied' => $left->toScale(2, RoundingMode::HalfUp)->__toString(),
				];
			}

		}

		if((int) count($update) > 0){
			$update = array_chunk($update, 50);
			$this->db->updateMultipleEntries($update, $type);
		}

	}

	/**
	 * updateInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @return void
	 */
	public function updateInvoice(int $company_id, int $invoice_id) : void {

		$entries = $this->db->fetchLedgerForApplying($company_id, $invoice_id);
		$invoice = $this->db->fetchInvoiceObjById($invoice_id, $company_id, ['sent_at', 'reminders_sent', 'total', 'balance_due']);

		$status = InvoiceStatus::DRAFT->value;

		if($invoice['sent_at'] !== null || (int) $invoice['reminders_sent'] > 0){
			$status = InvoiceStatus::SENT->value;
		}

		$sum = BigDecimal::of(0);
		foreach($entries as $entry){
			$sum = $sum->plus($entry['total_applied']);
		}

		$total = BigDecimal::of($invoice['total']);

		$new_balance_due = $total->minus($sum);
		$old_balance_due = BigDecimal::of($invoice['balance_due']);

		if($new_balance_due->isEqualTo($old_balance_due)){
			return ;
		}

		if($sum->isLessThan($total) && !$sum->isEqualTo(BigDecimal::of(0))){
			$status = InvoiceStatus::PARTIALLY_PAID->value;
		}else if($sum->isEqualTo($total)){
			$status = InvoiceStatus::PAID->value;
		}

		if($sum->isGreaterThan($total)){
			throw new InvoiceException('Something went wrong in calculation', 'unexpected_error_calc', (int) config('global.error_code'));
		}

		$update = [
			'status'		=>	$status,
			'balance_due'	=>  $new_balance_due->toScale(2, RoundingMode::HalfUp)->__toString(),	
		];

		$this->db->updateInvoiceForApply($company_id, $invoice_id, $update);

	}

	/**
	 * applyUnapplyCredits function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param array $applied
	 * @param array $removed_ids
	 * @return void
	 */
	public function applyUnapplyEntries(int $company_id, int $invoice_id, array $applied, array $removed_ids, string $type) : void {

		FacadesDB::transaction(function() use ($company_id, $invoice_id, $applied, $removed_ids, $type){

			//modify ledger first.
			$this->modifyLedger($company_id, $invoice_id, $applied, $removed_ids, $type);
			//now update entries
			$applied_ids = array_values(array_unique($this->getIds($applied)));
			$removed_ids = array_values(array_unique($removed_ids));
			$merged_ids = array_merge($applied_ids, $removed_ids);
			$this->updateEntries($company_id, $merged_ids, $type);
			$this->updateInvoice($company_id, $invoice_id);

			FacadesDB::afterCommit(function() use ($company_id, $invoice_id){
				GenerateInvoiceSnapshotJob::dispatch($company_id, $invoice_id, true, false);	
			});

		});
		
	}

}