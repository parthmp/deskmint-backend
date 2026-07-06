<?php

namespace App\Services\Invoice;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoicesCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Modules\EasyIndex\EasyIndex;
use App\Modules\InvoiceGeneration\InvoiceDBOperations;
use App\Modules\InvoiceGeneration\InvoiceSettingsResolver;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Repositories\Invoice\InvoiceRepository;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Services\HandleInvoiceNumbers;
use App\Services\Invoice\Exceptions\InvoiceException;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class InvoiceFetchService{

	public function __construct(
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSettingsService $invoice_settings_service,
		private CustomFields $custom_fields,
		private SettingsSectionRepository $settings_section_repository,
		private EasyIndex $easy_index,
		private InvoiceRepository $invoice_repository,
		private InvoiceSettingsResolver $invoice_settings_resolver,
		private InvoiceDBOperations $invoice_db_operations
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
			throw new InvoiceException("Invalid request", "invalid_timezone", config('global.error_code'));
		}

		$timezone_offset_minutes = (int) Sanitize::input($request->input('timezone_offset_minutes'));

		$invoice_settings = $this->invoice_settings_service->setCompany($company_id);

		$fields = $this->custom_fields->fetchCustomFields(InvoicesCustomField::class, $company_id);

		// /* get payment integration data */
		$gateways = $this->settings_section_repository->getGateWayNames((int) $company_id);

		return [
			'invoice_number'	=>	(new HandleInvoiceNumbers((int) $company_id, $invoice_settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->getNextInvoiceNumber(),
			'product_columns' 	=> 	$invoice_settings->getProductColumns(),
			'total_fields' 		=> 	$invoice_settings->getTotalFields(),
			'custom_fields'		=>	$this->custom_fields->printCustomFields($fields),
			'gateways'			=>	$gateways
		];
		
	}

	/**
	 * fetchIndex function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchIndex(Request $request) : array {
		$joins = [
					[
						'table' => 'invoices_flat',
						'first' => 'invoices.id',
						'operator' => '=',
						'second' => 'invoices_flat.invoice_id',
						'columns' => '' //this will be replaced by EasyIndex class.
					],
					// [
					// 	'table' => 'clients',
					// 	'first' => 'clients.id',
					// 	'operator' => '=',
					// 	'second' => 'invoices.client_id',
					// 	'columns' => ['clients.first_name as first_name', 'clients.last_name as last_name', 'clients.client_company_name as client_company']
					// ],
					[
						'table' => 'currencies',
						'first' => 'currencies.id',
						'operator' => '=',
						'second' => 'invoices.currency_id',
						'columns' => ['currencies.code as c_code']
					],
					// [
					// 	'table' => 'countries as b_countries',
					// 	'first' => 'clients.billing_country_id',
					// 	'operator' => '=',
					// 	'second' => 'b_countries.id',
					// 	'columns' => ['b_countries.country_name as b_country_name']
					// ],
					// [
					// 	'table' => 'countries as s_countries',
					// 	'first' => 'clients.shipping_country_id',
					// 	'operator' => '=',
					// 	'second' => 's_countries.id',
					// 	'columns' => ['s_countries.country_name as s_country_name']
					// ],
					// [
					// 	'table' => 'industries',
					// 	'first' => 'clients.industry_id',
					// 	'operator' => '=',
					// 	'second' => 'industries.id',
					// 	'columns' => ['industries.industry_name as industry_name']
					// ]
				];

			$default_columns = [
				'searchable_columns'	=>	['invoices.invoice_number', 'invoices.total', 'currencies.code', 'invoices.status', 'invoices.first_name', 'invoices.last_name', 'invoices.full_name'],
				'searchable_dates'		=>	['invoices.created_at', 'invoices.due_date', 'invoices.invoice_date'],
				'show_columns'			=>	[
					[
						'label'	=>	'invoice_number',
						'text'	=>	'Invoice#',
					],
					[
						'label'	=>	'invoice_date',
						'text'	=>	'Invoice date',
					],
					[
						'label'	=>	'due_date',
						'text'	=>	'Due date',
					],
					[
						'label'	=>	'total',
						'text'	=>	'Total',
					],
					[
						'label'	=>	'c_code',
						'text'	=>	'Currency',
					],
					[
						'label'	=>	'status',
	 					'text'	=>	'Status',
					],
					[
						'label'	=>	'full_name',
	 					'text'	=>	'Full name',
					],
					[
						'label'	=>	'created_at',
	 					'text'	=>	'Added on',
					]
				],
			];

			$rewrites = [
				'data' => [
					'invoices.discount_type' => [
						1	=>	'Percentage',
						2	=>	"Amount"
					],
					'invoices.status' => [
						InvoiceStatus::PENDING->value				=>	InvoiceStatus::PENDING->label(),
						InvoiceStatus::CANCELLED->value				=>	InvoiceStatus::CANCELLED->label(),
						InvoiceStatus::PARTIALLY_PAID->value		=>	InvoiceStatus::PARTIALLY_PAID->label(),
						InvoiceStatus::PAID->value					=>	InvoiceStatus::PAID->label(),
					],
					'invoices.payment_method' => [
						1	=>	'Cash',
						2	=>	'Netbanking',
						3	=>	'PayPal',
						4	=>	'Stripe',
					]
				],
				'ui'	=>	[
					'discount_type'	=>	[
						[
							'type'			=>	'label',
							'highlight'		=>	'success',
							'text'			=>	'Percentage',
							'value'			=>	1,
						],
						[
							'type'			=>	'label',
							'highlight'		=>	'success',
							'text'			=>	'Amount',
							'value'			=>	2,
						]
					],
					'status'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'error',
							'text'		=>	InvoiceStatus::CANCELLED->label(),
							'value'		=>	InvoiceStatus::CANCELLED->value,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	InvoiceStatus::PENDING->label(),
							'value'		=>	InvoiceStatus::PENDING->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	InvoiceStatus::PARTIALLY_PAID->label(),
							'value'		=>	InvoiceStatus::PARTIALLY_PAID->value
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'success',
							'text'		=>	InvoiceStatus::PAID->label(),
							'value'		=>	InvoiceStatus::PAID->value
						]
					],
					'payment_method'	=>	[
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Cash',
							'value'		=>	1,
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Netbanking',
							'value'		=>	2
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'PayPal',
							'value'		=>	3
						],
						[
							'type'		=>	'label',
							'highlight'	=>	'info',
							'text'		=>	'Stripe',
							'value'		=>	4
						]
					]
				]

			];

		return $this->easy_index->setType('invoice')->setCustomFieldClass(InvoicesCustomField::class)->setJoins($joins)->setExceptionClass(InvoiceException::class)->setRequest($request)->setDefaultColumns($default_columns)->setAdditionalSearchables([ /* map additional searchables here for deep joins */
			'c_code'				=>		'currencies.code'
		 ])->setRewrites($rewrites)->setModel(Invoice::class)->fetchIndex();
	}
	
	private function assembleProductRowsForEdit(int $company_id, int $invoice_id, int $timezone_offset_minutes) : array {

		$this->invoice_settings_resolver = $this->invoice_settings_resolver->setCompanyId($company_id)->setInvoiceId($invoice_id);

		$rows_settings = $this->invoice_settings_resolver->fetchCurrentRowsSettings();
		
		$items = $this->invoice_db_operations->setCompanyId($company_id)->setInvoiceId($invoice_id)->execRequiredSettings()->fetchInvoiceItemsWithCustomCols();
		
		$rows = [];
		$row_index = 0;

		foreach($items as $item){

			$temp = [];

			$temp['id'] = Str::uuid();
			$temp['row_index'] = $row_index;
			$temp['row_uuid'] = $item->row_uuid;
			$temp['line_subtotal'] = $item->line_subtotal;
			$temp['tax_amount'] = $item->tax_amount;
			$temp['line_total'] = $item->line_total;

			foreach($rows_settings as $rows_setting){

				if($rows_setting['type'] === 'normal'){
					if($rows_setting['value'] === 'unit_cost'){
						$rows_setting['value'] = 'unit_price';
					}

					$mapped = $rows_setting['mapped'];

					if($mapped[0] === 'tax'){
						$temp['tax'] = (float) $item->tax;
					}else{
						if($mapped[0] === 'product_id'){
							$temp['product_id'] = $item->product->id;
							$temp['item'] = $item->product->product_name;
						}else{
							$temp[$rows_setting['value']] = $item->{$mapped[0]};
						}
						
					}

				}else{
					//for custom product row fields.
					if((int) $rows_setting['tax'] === 1){

						//for tax fields
						$key = 'custom_tax_'.General::replaceWithUnderscores($rows_setting['text']);

					}else{
						$key = 'normal_'.General::replaceWithUnderscores($rows_setting['value']);
					}

					$temp[$key] = '';
					foreach($item->custom_field_values as $custom_field){
						if((string) $custom_field->row_uuid === (string) $item->row_uuid && (int) $custom_field->apc_field_id === (int) $rows_setting['id_column']){
							$temp[$key] = $custom_field->value;
						}
					}
				}	

				
			}

			$rows[] = $temp;

			$row_index++;

		}
		
		return $rows;

	}	

	/**
	 * fetchInvoice function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 * @param integer $timezone_offset_minutes
	 * @return array
	 */
	public function fetchInvoice(int $company_id, int $invoice_id, int $timezone_offset_minutes) : array {
		
		$invoice = $this->invoice_repository->fetchById($invoice_id);

		if(empty($invoice)){
			throw new InvoiceException('invalid invoice id', 'invalid_invoice_id', config('global.error_code'));
		}
		
		unset($invoice->pattern_matched);
		unset($invoice->scan_chars);
		unset($invoice->settings_snapshot);

		//$product_columns = $this->invoice_repository->fetchCustomProductColumnValues($invoice_id, $company_id);

		$custom_fields = $this->custom_fields->fetchCustomFieldValues($invoice_id, 'invoice', InvoiceCustomFieldValue::class);
		
		$product_rows = $this->assembleProductRowsForEdit($company_id, $invoice_id, $timezone_offset_minutes);

		return [
			'invoice'			=>	$invoice,
			'custom_fields' 	=> 	$custom_fields,
			'product_rows'		=>	$product_rows,
			'locked'			=>	$this->invoice_repository->ifInvoiceLocked($invoice_id)
		];

	}

	/**
	 * fetchSnapshot function
	 *
	 * @param integer $invoice_id
	 * @return array
	 */
	public function fetchSnapshot(int $invoice_id) : array {
		return $this->invoice_repository->fetchInvoiceSnapshot($invoice_id);
	}

}