<?php

namespace App\Repositories\RecurringInvoice;

use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use Illuminate\Http\Request;

class RecurringInvoiceRepository {
	
	/**
	 * fetchById function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @param array $selects
	 * @return RecurringInvoice|null
	 */
	public function fetchById(int $company_id, int $recurring_invoice_id, array $selects = ['*']) : ?RecurringInvoice {
		return RecurringInvoice::select(...$selects)->where([['company_id', '=', $company_id], ['id', '=', $recurring_invoice_id]])->first();
	}
	
	/**
	 * saveOrUpdate function
	 *
	 * @param array $data
	 * @param integer $company_id
	 * @param integer|null $recurring_invoice_id
	 * @return RecurringInvoice
	 */
	public function saveOrUpdate(array $data, int $company_id, ?int $recurring_invoice_id) : RecurringInvoice {

		if($recurring_invoice_id){
			$recurring_invoice = $this->fetchById($company_id, $recurring_invoice_id);
		}else{
			$recurring_invoice = new RecurringInvoice();
			$recurring_invoice->uuid = $data['uuid'];
			$recurring_invoice->company_id = $data['company_id'];
		}
		
		$recurring_invoice->client_id = $data['client_id'];
		$recurring_invoice->currency_id = $data['currency_id'];
		$recurring_invoice->po_number = $data['po_number'];
		$recurring_invoice->discount = $data['discount'];
		$recurring_invoice->discount_type = $data['discount_type'];
		$recurring_invoice->discount_amount_post_tax = $data['discount_amount_post_tax'];
		$recurring_invoice->discount_amount_pre_tax = $data['discount_amount_pre_tax'];
		$recurring_invoice->subtotal = $data['subtotal'];
		$recurring_invoice->tax_amount = $data['tax_amount'];
		$recurring_invoice->total = $data['total'];
		$recurring_invoice->status = $data['status'];
		$recurring_invoice->invoice_terms = $data['invoice_terms'];
		$recurring_invoice->payment_gateway = $data['payment_gateway'];
		$recurring_invoice->frequency = $data['frequency'];
		$recurring_invoice->custom_frequency_days = $data['custom_frequency_days'];
		$recurring_invoice->mark_paid_automatically = $data['mark_paid_automatically'];
		$recurring_invoice->timezone = $data['timezone'];
		$recurring_invoice->hidden_sent_at = $data['hidden_sent_at'];
		$recurring_invoice->save();

		return $recurring_invoice;

	}

	/**
	 * upsertRecurringInvoiceItems function
	 *
	 * @param array $items
	 * @param integer|null $recurring_invoice_id
	 * @return void
	 */
	public function upsertRecurringInvoiceItems(array $items, ?int $recurring_invoice_id) : void {
		
		if($recurring_invoice_id){
			RecurringInvoiceItem::where('recurring_invoice_id', '=', $recurring_invoice_id)->forceDelete();
		}

		$now = now();
		foreach($items as &$item){
			$item['created_at'] = $now;
			$item['updated_at'] = $now;
		}
		
		RecurringInvoiceItem::insert($items);
		
	}

}