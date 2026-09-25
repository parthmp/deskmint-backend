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
use App\Repositories\Client\ClientRepository;
use App\Repositories\RecurringInvoice\RecurringInvoiceRepository;
use App\Services\Invoice\InvoiceBaseService;
use App\Services\Invoice\InvoiceCalculationService;
use App\Services\Invoice\InvoiceSettingsService;
use App\Services\Invoice\InvoiceValidationService;
use App\Traits\InvoiceBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Str;

class RecurringInvoiceService {

	use InvoiceBase;

	public function __construct(
		private InvoiceValidationService $invoice_validation_service,
		private CustomFields $custom_fields,
		private InvoiceSettingsService $invoice_settings_service,
		private RecurringInvoiceValidationService $recurring_invoice_validation_service,
		private RecurringInvoiceRepository $recurring_invoice_repository,
		private ClientRepository $client_repository,
		private InvoiceBaseService $invoice_base_service,
		private InvoiceCalculationService $invoice_calculation_service
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

		$invoice_settings = $this->invoice_settings_service->setCompany($company_id);

		$fields = $this->custom_fields->fetchCustomFields(RecurringInvoicesCustomField::class, $company_id);

		$gateways = PaymentGateway::configuredOptions((int) $company_id);

		$freqs = Frequencies::dropdownData();
		
		return [
			'product_columns' 			=> 	$invoice_settings->getProductColumns(),
			'total_fields' 				=> 	$invoice_settings->getTotalFields(),
			'custom_fields'				=>	$this->custom_fields->printCustomFields($fields),
			'gateways'					=>	$gateways,
			'frequencies'				=>	$freqs,
			'custom_frequency_value'	=> Frequencies::CUSTOM->value,
			'none_gateway_value'		=> PaymentGateway::NONE->value
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
		$discount_type = DISCOUNT_TYPE_AMOUNT;
		if($discount_array['discount_type'] === 'percentage'){
			$discount_type = DISCOUNT_TYPE_PERCENTAGE;
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
		$frequency = (int) Sanitize::input($request->input('settings.frequency'));
		$custom_frequency_days = (int) Sanitize::input($request->input('settings.custom_frequency_days'));
		
		$mark_invoices_paid = (bool) Sanitize::input($request->input('settings.mark_invoices_paid'));
		$send_email = (bool) Sanitize::input($request->input('settings.send_email'));
		$timezone = (string) Sanitize::input($request->input('timezone'));

		$status = RecurringInvoiceStatus::DRAFT->value;
		if($send_email){
			$status = RecurringInvoiceStatus::SENT->value;
		}

		$settings = $this->invoice_settings_service->setCompany((int) $company_id);
		
		return [
			'uuid'						=>	Str::uuid(),
			'company_id'				=>	$company_id,
			'client_id'					=>	$client_id,
			'currency_id'				=>	$client->currency_id,
			'po_number'					=>	$po_number,
			'discount'					=>	$discount_array['discount_number'],
			'discount_type'				=>	$discount_type,
			'discount_amount_post_tax'	=>	$global_discount_amount_post_tax,
			'discount_amount_pre_tax'	=>	$global_discount_amount_pre_tax,
			'subtotal'					=>	$global_subtotal,
			'tax_amount'				=>	$global_tax_amount,
			'total'						=>	$global_total,
			'status'					=>	$status,
			'invoice_terms'				=>	$invoice_terms,
			'payment_gateway'			=>	$payment_gateway,
			'frequency'					=>	$frequency,
			'custom_frequency_days'		=>	$custom_frequency_days,
			'mark_paid_automatically'	=>	$mark_invoices_paid,
			'timezone'					=>	$timezone,
			'hidden_sent_at'			=>	now(),
			'rows'						=>	$product_rows,
			'settings_snapshot'			=>	json_encode($settings->getProductColumns())
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
	 * isCustomColumn function
	 *
	 * @param array $column
	 * @return boolean
	 */
	protected function isCustomColumn(array $column): bool {
        return ($column['mapped'] === null || $column['mapped'] === '') && $column['type'] === 'custom';
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
	 * @return void
	 */
	public function save(Request $request, int $company_id, ?int $recurring_invoice_id = null) : void {
		
		$add = true;
		if($recurring_invoice_id){
			$add = false;
		}

		DB::transaction(function () use ($request, $company_id, $recurring_invoice_id, $add) {

			$data = $this->assembleData($request, $company_id);
			$rows = $data['rows'];
			unset($data['rows']);

			$reccuring_invoice = $this->recurring_invoice_repository->saveOrUpdate($data, $company_id, $recurring_invoice_id);
			$this->upsertItems($reccuring_invoice->id, $rows);
			$this->custom_fields->upsertCustomFieldValues($request, $reccuring_invoice->id, RecurringInvoicesCustomField::class, RecurringInvoiceCustomFieldValue::class, 'recurring_invoices_flat', 'recurring_invoice', $add);
			$this->upsertCustomProductRows($request, (int) $reccuring_invoice->id, $company_id);

		});

	}

}