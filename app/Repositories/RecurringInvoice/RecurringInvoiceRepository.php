<?php

namespace App\Repositories\RecurringInvoice;

use App\Models\AdditionalProductColumnsField;
use App\Models\AdditionalProductColumnsFieldValueRi;
use App\Models\ClientCustomFieldValue;
use App\Models\PaymentType;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceCustomFieldValue;
use App\Models\RecurringInvoiceItem;
use App\Models\RecurringInvoiceUrl;
use App\Models\SubscriptionPlan;
use App\Modules\Payment\Enums\PaymentGateway;
use Illuminate\Database\Eloquent\Collection;
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
	 * fetchWithJoin function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return RecurringInvoice|null
	 */
	public function fetchWithJoin(int $company_id, int $recurring_invoice_id) : ?RecurringInvoice {
		return RecurringInvoice::select('recurring_invoices.*', 'currencies.id as currency_id', 'currencies.code as currency_code', 'clients.first_name', 'clients.last_name')->join('clients', 'clients.id', '=', 'recurring_invoices.client_id')->join('currencies', 'currencies.id', '=', 'clients.currency_id')->where([['recurring_invoices.company_id', '=', $company_id], ['recurring_invoices.id', '=', $recurring_invoice_id]])->first();
	}

	/**
	 * deleteRecurringUrls function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return void
	 */
	public function deleteRecurringUrls(int $company_id, int $recurring_invoice_id) : void {
		RecurringInvoiceUrl::where([['company_id', '=', $company_id], ['recurring_invoice_id', '=', $recurring_invoice_id]])->forceDelete();
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

			if((int) $recurring_invoice->payment_gateway === PaymentGateway::NONE->value){
				$recurring_invoice->client_id = $data['client_id'];
				$recurring_invoice->currency_id = $data['currency_id'];
			}

		}else{
			$recurring_invoice = new RecurringInvoice();
			$recurring_invoice->uuid = $data['uuid'];
			$recurring_invoice->company_id = $data['company_id'];
			$recurring_invoice->status = $data['status'];
			$recurring_invoice->payment_gateway = $data['payment_gateway'];
			$recurring_invoice->client_id = $data['client_id'];
			$recurring_invoice->currency_id = $data['currency_id'];
		}
	
		
		
		$recurring_invoice->po_number = $data['po_number'];
		$recurring_invoice->discount = $data['discount'];
		$recurring_invoice->discount_type = $data['discount_type'];
		$recurring_invoice->discount_amount_post_tax = $data['discount_amount_post_tax'];
		$recurring_invoice->discount_amount_pre_tax = $data['discount_amount_pre_tax'];
		$recurring_invoice->subtotal = $data['subtotal'];
		$recurring_invoice->tax_amount = $data['tax_amount'];
		$recurring_invoice->total = $data['total'];
		
		$recurring_invoice->invoice_terms = $data['invoice_terms'];
		
		$recurring_invoice->payment_type_id = $data['payment_type_id'];
		$recurring_invoice->frequency = $data['frequency'];
		$recurring_invoice->custom_frequency_days = $data['custom_frequency_days'];
		$recurring_invoice->mark_paid_automatically = $data['mark_paid_automatically'];
		$recurring_invoice->timezone = $data['timezone'];
		$recurring_invoice->send_invoice_email = $data['send_invoice_email'];
		$recurring_invoice->next_invoice_on = $data['next_invoice_on'];
		$recurring_invoice->settings_snapshot = $data['settings_snapshot'];
		$recurring_invoice->hidden_sent_at = $data['hidden_sent_at'];
		$recurring_invoice->save();

		$this->deleteRecurringUrls((int) $data['company_id'], (int) $recurring_invoice->id);

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

	/**
	 * getCustomTaxIds function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function getCustomTaxIds(int $company_id) : array{
		return AdditionalProductColumnsField::where([['company_id', '=', $company_id], ['type', '=', 'tax']])->pluck('id')->toArray();
	}

	/**
	 * upsertAdditionalColumnFieldValues function
	 *
	 * @param array $rows
	 * @param array $unique
	 * @param array $values
	 * @return void
	 */
	public function upsertAdditionalColumnFieldValues(array $rows, array $unique, array $values) : void {
		AdditionalProductColumnsFieldValueRi::upsert($rows, $unique, $values);
	}

	/**
	 * paymentTypeExists function
	 *
	 * @param integer $payment_type_id
	 * @return boolean
	 */
	public function paymentTypeExists(int $payment_type_id) : bool {

		$record = PaymentType::where('id', '=', $payment_type_id)->first();
		if(!$record){
			return false;
		}

		return true;

	}

	/**
	 * fetchRecurringInvoiceItemsWithCustomCols function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return Collection
	 */
	public function fetchRecurringInvoiceItemsWithCustomCols(int $company_id, int $recurring_invoice_id) : Collection {
		return RecurringInvoiceItem::where([['recurring_invoice_id', '=', $recurring_invoice_id]])
								->withTrashed()
								->with(['product', 'custom_field_values.custom_product_field' => function($query) use($company_id) {
        								$query->where('company_id', $company_id);
    							}])
								->get();
	}

	/**
	 * fetchSubScriptionPlan function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return SubscriptionPlan|null
	 */
	public function fetchSubscriptionPlan(int $company_id, int $recurring_invoice_id) : ?SubscriptionPlan {
		return SubscriptionPlan::where([['company_id', '=', $company_id], ['recurring_invoice_id', '=', $recurring_invoice_id]])->orderBy('id', 'desc')->first();
	}

	/**
	 * fetchByIdWithCurrency function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return RecurringInvoice|null
	 */
	public function fetchByIdWithCurrency(int $company_id, int $recurring_invoice_id) : ?RecurringInvoice {
		return RecurringInvoice::where([['company_id', '=', $company_id], ['id', '=', $recurring_invoice_id]])->with('currency')->first();
	}

	/**
	 * updateNextInvoiceOn function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @param string $datetime
	 * @return void
	 */
	public function updateNextInvoiceOn(int $company_id, int $recurring_invoice_id, string $datetime) : void {
		RecurringInvoice::where([['company_id', '=', $company_id], ['id', '=', $recurring_invoice_id]])->update([
			'next_invoice_on'	=>	$datetime
		]);
	}

	/**
	 * fetchClientCustomFieldValuesForView function
	 *
	 * @param integer $client_id
	 * @return Collection|null
	 */
	public function fetchClientCustomFieldValuesForView(int $client_id) : ?Collection {

		$data = ClientCustomFieldValue::where('client_id', '=',$client_id)->withTrashed()->with(['clients_custom_field_wt.custom_field_type_wt'])->get();
		return $data;

	}

	/**
	 * fetchCustomFieldValuesOfRecurringInvoice function
	 *
	 * @param integer $recurring_invoice_id
	 * @return Collection|null
	 */
	public function fetchCustomFieldValuesOfRecurringInvoice(int $recurring_invoice_id) : ?Collection {
		
		return RecurringInvoiceCustomFieldValue::where('recurring_invoice_id', '=',$recurring_invoice_id)
										->withTrashed()
										->with([
											'recurring_invoices_custom_field_wt' => function ($query) {
												$query->withTrashed()
													->with(['custom_field_type_wt' => function ($q) {
														$q->withTrashed();
													}]);
											},
										])
										->get();
	}

	/**
	 * fetchItemsWithCustomCols function
	 *
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return Collection
	 */
	public function fetchItemsWithCustomCols(int $company_id, int $recurring_invoice_id) : Collection {

		return RecurringInvoiceItem::where('recurring_invoice_id', '=', $recurring_invoice_id)
					->withTrashed()
					->with([
						'product',
						'custom_field_values_ri' => function ($query) use ($recurring_invoice_id, $company_id) {
							$query->withTrashed()->where('recurring_invoice_id', '=', $recurring_invoice_id)
								->whereHas('custom_product_field', function ($q) use ($company_id) {
									$q->withTrashed()
										->where('company_id', $company_id);
								})
								->with(['custom_product_field' => function ($q) use ($company_id) {
									$q->withTrashed()
										->where('company_id', $company_id);
								}]);
						},
					])
					->get();
	
	}

	/**
	 * fetchRecurringInvoiceDataForSnapshot function
	 *
	 * @param integer $recurring_invoice_id
	 * @param array $selects
	 * @return RecurringInvoice
	 */
	public function fetchRecurringInvoiceDataForSnapshot(int $recurring_invoice_id, $selects = ['*']) : RecurringInvoice {
		return RecurringInvoice::select(...$selects)->where('id', '=', $recurring_invoice_id)->with('client_wt', 'currency', 'client_wt.billing_country', 'company_wt', 'client_wt.currency')->withTrashed()->first();
	}


}