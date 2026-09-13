<?php

namespace App\Modules\ApplyUnapply\CreditsAndPayments;

use App\Enums\Credits\CreditStatus;
use App\Helpers\Sanitize;
use App\Jobs\GenerateInvoiceSnapshotJob;
use App\Modules\ApplyUnapply\Common\Traits\ApplyUnapplyCommon;
use App\Modules\ApplyUnapply\CreditsAndPayments\DB\DB;
use App\Modules\ApplyUnapply\CreditsAndPayments\Validation\Validation;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as FacadesDB;

class ApplyUnapplyForCreditsAndPayments {

	use ApplyUnapplyCommon;

	public function __construct(
		private DB $db,
		private Validation $validation
	){}


	/**
	 * fetchAlreadyAppliedInvoicesForEntry function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param string $type
	 * @return array
	 */
	public function fetchAlreadyAppliedInvoicesForEntry(int $company_id, int $id, string $type) : array {
		return $this->db->fetchAlreadyAppliedInvoicesForEntry($company_id, $id, $type);
	}

	/**
	 * fetchEntryWithCurrencyInfo function
	 *
	 * @param integer $company_id
	 * @param integer $id
	 * @param string $type
	 * @return array
	 */
	public function fetchEntryWithCurrencyInfo(int $company_id, int $id, string $type) : array {
		return $this->db->fetchEntryWithCurrencyInfo($company_id, $id, $type);
	}

	/**
	 * searchInvoices function
	 *
	 * @param integer $company_id
	 * @param integer $currency_id
	 * @param integer $client_id
	 * @param integer $id
	 * @param array $applied_ids
	 * @param array $paid_ids
	 * @param string $searched
	 * @param string $type
	 * @return array
	 */
	public function searchInvoices(int $company_id, int $currency_id, int $client_id, int $id, array $applied_ids, array $paid_ids, string $searched, string $type) : array {
		return $this->db->searchInvoices($company_id, $currency_id, $client_id, $id, $applied_ids, $paid_ids, $searched, $type);
	}
	
	/**
	 * validateApplyUnapply function
	 *
	 * @param Request $request
	 * @param string $type
	 * @return boolean
	 */
	public function validateApplyUnapply(Request $request, string $type) : bool {
		return $this->validation->validateApplyUnapply($request, $type);
	}

	/**
	 * modifyLedger function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param array $applied
	 * @param array $removed_ids
	 * @param string $exeption
	 * @param string $type
	 * @return void
	 */
	private function modifyLedger(int $company_id, int $entry_id, array $applied, array $removed_ids, string $exeption, string $type){

		//entry_id = credit or payment id
		//applied = array with info, id in it means invoice id
		//removed_ids = array of removed invoice ids from frontend for credit or payment.
		$this->db->removeLedgerEntries($company_id, $entry_id, $removed_ids, $type);

		$applied_ids = $this->getIds($applied, $exeption);
		$entries = $this->db->fetchAppliedEntries($company_id, $entry_id, $applied_ids, $type);

		$insert = [];
		$update = [];

		foreach($applied as $row){

			$found = false;

			foreach($entries as $entry){

				if((int) $row['id'] === (int) $entry['invoice_id']){

					$found = true;

					$applied_amount_db = BigDecimal::of($entry['total_applied']);
					$applied_amount_frontend = BigDecimal::of($row['amount']);

					if(!$applied_amount_db->isEqualTo($applied_amount_frontend)){
						if($type === 'credit'){
							$update[] = [
								'id'							=>	$entry['id'],
								'applied_amount_from_payments'	=>	0,
								'applied_amount_from_credits'	=>	$applied_amount_frontend->toScale(2, RoundingMode::HalfUp)->__toString(),
								'total_applied'					=>	$applied_amount_frontend->toScale(2, RoundingMode::HalfUp)->__toString(),
							];
						}else if($type === 'payment'){
							$update[] = [
								'id'							=>	$entry['id'],
								'applied_amount_from_payments'	=>	$applied_amount_frontend->toScale(2, RoundingMode::HalfUp)->__toString(),
								'applied_amount_from_credits'	=>	0,
								'total_applied'					=>	$applied_amount_frontend->toScale(2, RoundingMode::HalfUp)->__toString(),
							];
						}
						
					}

				}

			}


			if(!$found){

				$temp_insert = [
					'company_id'						=>	$company_id,
					'invoice_id'						=>	(int) $row['id'],
					'total_applied'						=>	$row['amount'],
					'created_at'						=>	now(),
					'updated_at'						=>	now()
				];

				if($type === 'credit'){

					$temp_insert['payment_id'] = null;
					$temp_insert['credit_id'] = $entry_id;
					$temp_insert['applied_amount_from_payments'] = 0;
					$temp_insert['applied_amount_from_credits'] = $row['amount'];

				}else if($type === 'payment'){

					$temp_insert['payment_id'] = $entry_id;
					$temp_insert['credit_id'] = null;
					$temp_insert['applied_amount_from_payments'] = $row['amount'];
					$temp_insert['applied_amount_from_credits'] = 0;

				}

				$insert[] = $temp_insert;

			}


		}

		//now need chunks.
		if((int) count($insert) > 0){
			$insert = array_chunk($insert, 50);
			$this->insertNewLedgerEntries($insert);
		}
		
		if((int) count($update) > 0){
			$update = array_chunk($update, 50);
			$this->updateLedgerEntries($update);
		}


	}

	/**
	 * modifyEntry function
	 *
	 * @param integer $company_id
	 * @param integer $entry_id
	 * @param string $exception
	 * @param string $type
	 * @return void
	 */
	private function modifyEntry(int $company_id, int $entry_id, string $exception, string $type) : void {

		$ledger = $this->db->fetchLedgerEntriesForEntry($company_id, $entry_id, $type);
		$entry_details = $this->db->fetchEntryDetails($company_id, $entry_id, $type);

		$sum = BigDecimal::of(0);

		foreach($ledger as $row){
			$sum = $sum->plus(BigDecimal::of($row['total_applied']));
		}

		$total = BigDecimal::of($entry_details['amount']);

		$not_applied_status = CreditStatus::NOT_APPLIED->value;
		$partially_applied_status = CreditStatus::PARTIALLY_APPLIED->value;
		$applied_status = CreditStatus::APPLIED->value;

		if($type === 'payment'){
			$not_applied_status = PaymentStatus::NOT_APPLIED->value;
			$partially_applied_status = PaymentStatus::PARTIALLY_APPLIED->value;
			$applied_status = PaymentStatus::APPLIED->value;
		}

		
		$status = $not_applied_status;

		if($sum->isLessThan($total) && $sum->isGreaterThan(BigDecimal::of(0))){
			$status = $partially_applied_status;
		}else if($sum->isEqualTo($total)){
			$status = $applied_status;
		}

		$left = $total->minus($sum);

		if($left->isLessThan(BigDecimal::of(0))){
			throw new $exception('Something went wrong in calculation', 'unexpected_error_calc', (int) config('global.error_code'));
		}

		$this->db->updateEntry($company_id, $entry_id, $status, $sum->toScale(2, RoundingMode::HalfUp)->__toString(), $left->toScale(2, RoundingMode::HalfUp)->__toString(), $type);


	}

	/**
	 * modifyInvoices function
	 *
	 * @param integer $company_id
	 * @param array $invoice_ids
	 * @return array
	 */
	private function modifyInvoices(int $company_id, array $invoice_ids) : array {

		$update = [];
		$updated_ids = [];

		$invoices_ledger_entries = $this->db->fetchInvoicesLedgerEntries($company_id, $invoice_ids);
		$invoices = $this->db->fetchInvoiceDataByIds($company_id, $invoice_ids, ['id', 'total', 'balance_due', 'sent_at', 'reminders_sent']);

		foreach($invoices as $invoice){

			$sum = BigDecimal::of(0);

			foreach($invoices_ledger_entries as $ledger_entry){
				if((int) $invoice['id'] === (int) $ledger_entry['invoice_id']){
					$sum = $sum->plus($ledger_entry['total_applied']);
				}
			}

			$balance_due_db = BigDecimal::of($invoice['balance_due']);
			$total_db = BigDecimal::of($invoice['total']);

			$new_balance_due = $total_db->minus($sum);

			if(!$new_balance_due->isEqualTo($balance_due_db)){

				$updated_ids[] = (int) $invoice['id'];

				$status = InvoiceStatus::DRAFT->value;

				if($invoice['sent_at'] !== null || (int) $invoice['reminders_sent'] > 0){
					$status = InvoiceStatus::SENT->value;
				}

				if($sum->isLessThan($total_db) && !$sum->isEqualTo(BigDecimal::of(0))){
					$status = InvoiceStatus::PARTIALLY_PAID->value;
				}else if($sum->isEqualTo($total_db)){
					$status = InvoiceStatus::PAID->value;
				}

				$update[] = [
					'id'			=>	$invoice['id'],
					'status'		=>	$status,
					'balance_due'	=>	$new_balance_due->toScale(2, RoundingMode::HalfUp)->__toString(),
				];

			}

		}

		if((int) count($update) > 0){
			$update = array_chunk($update, 50);
			$this->db->updateInvoices($update);
		}

		return $updated_ids;

	}


	public function applyUnapplyToInvoices(int $company_id, int $entry_id, array $applied, array $removed_ids, string $exception, string $type) : void {

		FacadesDB::transaction(function() use ($company_id, $entry_id, $applied, $removed_ids, $exception, $type) {

			$this->modifyLedger($company_id, $entry_id, $applied, $removed_ids, $exception, $type); //remove ledger entries and add new ones.
			$this->modifyEntry($company_id, $entry_id, $exception, $type);
			$invoice_ids = $this->getIds($applied, $exception);
			$merged = array_values(array_unique(array_merge($invoice_ids, $removed_ids)));
			$ids = $this->modifyInvoices($company_id, $merged);
			
			FacadesDB::afterCommit(function() use ($company_id, $ids) {
				foreach($ids as $id){
					$invoice_id = (int) Sanitize::input($id);
					GenerateInvoiceSnapshotJob::dispatch($company_id, $invoice_id, true, false);					
				}
			});
			
		});

	}


}