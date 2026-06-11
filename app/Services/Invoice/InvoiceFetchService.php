<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use App\Models\Invoice;
use App\Models\InvoicesCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Modules\EasyIndex\EasyIndex;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Services\HandleInvoiceNumbers;
use App\Services\Invoice\Exceptions\InvoiceException;
use Illuminate\Http\Request;

class InvoiceFetchService{

	public function __construct(
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSettingsService $invoice_settings_service,
		private CustomFields $custom_fields,
		private SettingsSectionRepository $settings_section_repository,
		private EasyIndex $easy_index
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
					[
						'table' => 'clients',
						'first' => 'clients.id',
						'operator' => '=',
						'second' => 'invoices.client_id',
						'columns' => ['clients.first_name as first_name', 'clients.last_name as last_name', 'clients.client_company_name as client_company']
					],
					[
						'table' => 'currencies',
						'first' => 'clients.currency_id',
						'operator' => '=',
						'second' => 'currencies.id',
						'columns' => ['currencies.code as c_code']
					],
					[
						'table' => 'countries as b_countries',
						'first' => 'clients.billing_country_id',
						'operator' => '=',
						'second' => 'b_countries.id',
						'columns' => ['b_countries.country_name as b_country_name']
					],
					[
						'table' => 'countries as s_countries',
						'first' => 'clients.shipping_country_id',
						'operator' => '=',
						'second' => 's_countries.id',
						'columns' => ['s_countries.country_name as s_country_name']
					],
					[
						'table' => 'industries',
						'first' => 'clients.industry_id',
						'operator' => '=',
						'second' => 'industries.id',
						'columns' => ['industries.industry_name as industry_name']
					]
				];

			$default_columns = [
				'searchable_columns'	=>	['invoices.invoice_number', 'invoices.total', 'currencies.code', 'invoices.is_paid', 'clients.first_name', 'clients.last_name'],
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
						'label'	=>	'is_paid',
	 					'text'	=>	'Paid',
					],
					[
						'label'	=>	'first_name',
	 					'text'	=>	'First name',
					],
					[
						'label'	=>	'last_name',
	 					'text'	=>	'Last name',
					],
					[
						'label'	=>	'created_at',
	 					'text'	=>	'Added on',
					]
				],
			];

		return $this->easy_index->setType('invoice')->setCustomFieldClass(InvoicesCustomField::class)->setJoins($joins)->setExceptionClass(InvoiceException::class)->setRequest($request)->setDefaultColumns($default_columns)->setModel(Invoice::class)->fetchIndex();
	}

}