<?php

namespace App\Services\RecurringInvoice;

use App\Enums\RecurringInvoices\Frequencies;
use App\Enums\RecurringInvoices\RecurringInvoiceStatus;
use App\Exceptions\RecurringInvoiceException;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceCustomFieldValue;
use App\Models\RecurringInvoicesCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\RecurringInvoice\DB\DB;
use App\Modules\RecurringInvoice\RecurringInvoice as RecurringInvoiceModule;
use App\Repositories\Client\ClientRepository;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\PaymentType\PaymentTypeRepository;
use App\Repositories\RecurringInvoice\RecurringInvoiceRepository;
use App\Services\Invoice\InvoiceBaseService;
use App\Services\Invoice\InvoiceCalculationService;
use App\Services\Invoice\InvoiceValidationService;
use App\Traits\InvoiceBase;
use Illuminate\Http\Request;
use \Illuminate\Support\Str;

class RecurringInvoiceService {

	use InvoiceBase;

	public function __construct(
		private InvoiceValidationService $invoice_validation_service,
		private CustomFields $custom_fields,
		private RecurringInvoiceValidationService $recurring_invoice_validation_service,
		private RecurringInvoiceRepository $recurring_invoice_repository,
		private ClientRepository $client_repository,
		private InvoiceBaseService $invoice_base_service,
		private InvoiceCalculationService $invoice_calculation_service,
		private RecurringInvoiceModule $reccuring_invoice_module,
		private PaymentTypeRepository $payment_type_repository,
		private InvoiceRepository $invoice_repository,
		private DB $db
	){}

	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchInitialData(Request $request, int $company_id) : array {

		if(!$this->invoice_validation_service->validateTimezoneOffeset($request)){
			throw new RecurringInvoiceException("Invalid request", "invalid_timezone", config('global.error_code'));
		}

		$timezone = (string) Sanitize::input($request->input('timezone'));

		
		$all_settings = $this->db->fetchSettings((int) $company_id);
		$product_column_settings = $this->db->fetchProductRowsSettings(null, $all_settings, (int) $company_id);
		
		$fields = $this->custom_fields->fetchCustomFields(RecurringInvoicesCustomField::class, $company_id);

		$gateways = PaymentGateway::configuredOptions((int) $company_id);

		$freqs = Frequencies::dropdownData();

		$payment_types = General::generateDropdownData($this->payment_type_repository->fetchAllPaymentTypes()->toArray(), 'payment_type', 'id');
		
		return [
			'product_columns' 			=> 	$product_column_settings,
			'custom_fields'				=>	$this->custom_fields->printCustomFields($fields),
			'gateways'					=>	$gateways,
			'frequencies'				=>	$freqs,
			'payment_types'				=>	$payment_types,
			'custom_frequency_value'	=> 	Frequencies::CUSTOM->value,
			'none_gateway_value'		=> 	PaymentGateway::NONE->value
		];

	}

	/**
	 * validate function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return boolean
	 */
	public function validate(Request $request, int $company_id) : bool {
		return $this->recurring_invoice_validation_service->validate($request, $company_id);
	}

	/**
	 * assembleData function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return array
	 */
	private function assembleData(Request $request, int $company_id) : array {

		$client_id = (int) Sanitize::input($request->input('data.invoice_details.client.client_id'));
		$client = $this->client_repository->fetchById($client_id, ['currency_id']);

		$po_number = '';
		if($request->has('data.invoice_details.po_number')){
			$po_number = (string) Sanitize::input($request->input('data.invoice_details.po_number'));
		}
		
		$discount_array = $this->invoice_base_service->getDiscountNumberAndType($request);
		$discount_type = 'amount';
		if($discount_array['discount_type'] === 'percentage'){
			$discount_type = 'percentage';
		}

		$product_rows = $this->filterValidProductRows($request->input('data.product_rows'), $company_id);

		$totals = $this->invoice_calculation_service->calculateInvoice($product_rows, $discount_type, $discount_array['discount_number']);
		$global_total = $totals['global_total'];
		$global_subtotal = $totals['global_subtotal'];
		$global_tax_amount = $totals['global_tax_amount'];
		$global_discount_amount_post_tax = $totals['global_discount_amount_post_tax'];
		$global_discount_amount_pre_tax = $totals['global_discount_amount_pre_tax'];

		$invoice_terms = '';
		if($request->filled('data.invoice_terms')){
			$invoice_terms = Sanitize::input($request->input('data.invoice_terms') ?? '');
		}

		$payment_gateway = (int) Sanitize::input($request->input('settings.payment_gateway'));

		$subscription_start_datetime = (string) Sanitize::input($request->input('settings.subscription_start_datetime'));

		$payment_type = null;
		$next_invoice_on = null;
		if($payment_gateway === PaymentGateway::NONE->value){
			$payment_type = (int) Sanitize::input($request->input('settings.payment_type'));
			$next_invoice_on = General::formatTimezoneDatetime($subscription_start_datetime, 'UTC', true, true);
		}

		
		$frequency = (int) Sanitize::input($request->input('settings.frequency'));
		$custom_frequency_days = (int) Sanitize::input($request->input('settings.custom_frequency_days'));
		
		$mark_invoices_paid = (bool) Sanitize::input($request->input('settings.mark_invoices_paid'));
		//$send_email = (bool) Sanitize::input($request->input('settings.send_email'));
		$timezone = (string) Sanitize::input($request->input('timezone'));

		$send_email_for_invoice = (bool) $request->input('settings.send_email_for_invoice');
		
		
		if($send_email_for_invoice){
			$send_email_for_invoice = 1;
		}else{
			$send_email_for_invoice = 0;
		}
		$status = RecurringInvoiceStatus::DRAFT->value;
		// always use draft, will update status if sending successes.
		// if($send_email){
		// 	$status = RecurringInvoiceStatus::SENT->value;
		// }

		$all_settings = $this->db->fetchSettings($company_id);

		$snapshot_data['general_settings'] = $this->db->fetchGeneralSettings($all_settings);
		$snapshot_data['client_settings'] = $this->db->fetchClientDetails($all_settings);
		$snapshot_data['company_settings'] = $this->db->fetchCompanyDetails($company_id, $all_settings);
		$snapshot_data['invoice_settings'] = $this->db->fetchInvoiceDetails($company_id, $all_settings);
		$snapshot_data['product_column_settings'] = $this->db->fetchProductRowsSettings(null, $all_settings, (int) $company_id);
		$snapshot_data['total_field_settings'] = $this->db->fetchTotalFieldsDetails($all_settings);
		$company = $this->db->fetchCompany((int) $company_id, ['logo', 'invoice_terms', 'invoice_footer'])->toArray();
		$snapshot_data['company_logo'] = $company['logo'] ?? '';
		$snapshot_data['terms'] = $company['invoice_terms'] ?? '';
		$snapshot_data['footer'] = $company['invoice_footer'] ?? '';

		
		
		return [
			'uuid'						=>	Str::uuid(),
			'company_id'				=>	$company_id,
			'client_id'					=>	$client_id,
			'currency_id'				=>	$client->currency_id,
			'po_number'					=>	$po_number,
			'discount'					=>	$discount_array['discount_number'],
			'discount_type'				=>	($discount_type === 'amount') ? DISCOUNT_TYPE_AMOUNT : DISCOUNT_TYPE_PERCENTAGE,
			'discount_amount_post_tax'	=>	$global_discount_amount_post_tax,
			'discount_amount_pre_tax'	=>	$global_discount_amount_pre_tax,
			'subtotal'					=>	$global_subtotal,
			'tax_amount'				=>	$global_tax_amount,
			'total'						=>	$global_total,
			'status'					=>	$status,
			'invoice_terms'				=>	$invoice_terms,
			'payment_gateway'			=>	$payment_gateway,
			'payment_type_id'			=>	$payment_type,
			'frequency'					=>	$frequency,
			'custom_frequency_days'		=>	$custom_frequency_days,
			'mark_paid_automatically'	=>	$mark_invoices_paid,
			'timezone'					=>	$timezone,
			'send_invoice_email'		=>	$send_email_for_invoice,
			'next_invoice_on'			=>	$next_invoice_on,
			'hidden_sent_at'			=>	now(),
			'rows'						=>	$totals['rows'],
			'settings_snapshot'			=>	json_encode($snapshot_data)
		];
	}

	private function upsertItems(int $recurring_invoice_id, array $rows) : void {

		$items = [];
		foreach($rows as $row){

			$temp = [];

			$temp['row_uuid'] = (string) Sanitize::input($row['row_uuid']);
			$temp['recurring_invoice_id'] = (int) $recurring_invoice_id;
			$temp['product_id'] = (int) Sanitize::input($row['product_id']);
			$temp['description'] = (string) Sanitize::input($row['description'] ?? '');
			$temp['unit_price'] = Sanitize::input($row['unit_price'] ?? 0);

			$temp['discount'] = Sanitize::input($row['discount'] ?? 0);
			$temp['discount_amount'] = Sanitize::input($row['discount_amount'] ?? 0);

			$temp['quantity'] = (int) Sanitize::input($row['quantity'] ?? 1);
			$temp['tax'] = Sanitize::input($row['tax'] ?? 0);
			$temp['tax_amount'] = Sanitize::input($row['tax_amount'] ?? 0);
			$temp['line_subtotal'] = Sanitize::input($row['line_subtotal'] ?? 0);
			$temp['line_total'] = Sanitize::input($row['line_total'] ?? 0);

			$items[] = $temp;
			
		}

		$this->recurring_invoice_repository->upsertRecurringInvoiceItems($items, $recurring_invoice_id);

	}

	/**
	 * prepareInsertData function
	 *
	 * @param array $product_rows
	 * @param integer $company_id
	 * @param integer $recurring_invoice_id
	 * @return array
	 */
	protected function prepareInsertData(array $product_rows, int $company_id, int $recurring_invoice_id): array {

		$recurring_invoice = $this->recurring_invoice_repository->fetchById($company_id, $recurring_invoice_id, ['settings_snapshot']);
		$snapshot = json_decode($recurring_invoice->settings_snapshot, true);
		$snapshot = $snapshot['product_column_settings'];

		$insert = [];

		$custom_tax_ids = $this->recurring_invoice_repository->getCustomTaxIds($company_id);

		foreach($snapshot as $user_defined_column){
			
			if(General::isCustomColumn($user_defined_column)){
				
				$custom_field_name = General::generateFieldName($user_defined_column, $custom_tax_ids);

				foreach($product_rows as $row){
					$temp = [];
					$temp['row_uuid'] = Sanitize::input($row['row_uuid']);
					$temp['recurring_invoice_id'] = (int) $recurring_invoice_id;
					$temp['apc_field_id'] = $user_defined_column['id_column'];
					$value = $row[$custom_field_name] ?? '';
					$temp['value'] = Sanitize::input($value);
					$insert[] = $temp;
				}

				

			}
			
			
		}

		return $insert;

	}

	/**
	 * upsertCustomProductRows function
	 *
	 * @param Request $request
	 * @param integer $recurring_invoice_id
	 * @param integer $company_id
	 * @return void
	 */
	public function upsertCustomProductRows(Request $request, int $recurring_invoice_id, int $company_id) : void {

		$product_rows_path = 'data.product_rows';
		$product_rows = $request->input($product_rows_path);
		$rows = $this->prepareInsertData($product_rows, $company_id, $recurring_invoice_id);
		$this->recurring_invoice_repository->upsertAdditionalColumnFieldValues($rows, ['row_uuid', 'recurring_invoice_id', 'apc_field_id'], ['value']);

	}

	/**
	 * save function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @param integer|null $recurring_invoice_id
	 * @return RecurringInvoice
	 */
	public function save(Request $request, int $company_id, ?int $recurring_invoice_id = null) : RecurringInvoice {
		
		$add = true;
		if($recurring_invoice_id){
			$add = false;
		}

		$data = $this->assembleData($request, $company_id);
		$rows = $data['rows'];
		unset($data['rows']);

		$reccuring_invoice = $this->recurring_invoice_repository->saveOrUpdate($data, $company_id, $recurring_invoice_id);
		$this->upsertItems($reccuring_invoice->id, $rows);
		$this->custom_fields->upsertCustomFieldValues($request, $reccuring_invoice->id, RecurringInvoicesCustomField::class, RecurringInvoiceCustomFieldValue::class, 'recurring_invoices_flat', 'recurring_invoice', $add);
		$this->upsertCustomProductRows($request, (int) $reccuring_invoice->id, $company_id);

		return $reccuring_invoice;

	}

	/**
	 * executeActions function
	 *
	 * @param integer $company_id
	 * @param array $ids
	 * @param boolean $send_email
	 * @param boolean $start_subscription
	 * @return void
	 */
	public function executeActions(int $company_id, array $ids, bool $send_email, bool $start_subscription) : void {
		$this->reccuring_invoice_module->setCompanyId($company_id)->setRecurringInvoiceIds($ids)->executeActions($send_email, $start_subscription);
		//$this->reccuring_invoice_module->setCompanyId($company_id)->setRecurringInvoiceIds($ids)->executeActions($send_email, false);
	}

}