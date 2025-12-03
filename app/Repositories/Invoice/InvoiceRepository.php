<?php

namespace App\Repositories\Invoice;

use App\Helpers\Sanitize;
use App\Models\Client;
use App\Models\InvoicesCustomField;
use App\Services\HandleInvoiceNumbers;
use App\Services\InvoiceSettingsService;
use App\Traits\CustomFieldsPrinting;
use App\Traits\PaymentGatewayDetails;
use Illuminate\Http\Request;

class InvoiceRepository{

	use PaymentGatewayDetails, CustomFieldsPrinting;

	/**
	 * getInitialData function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @param integer $timezone_offset_minutes
	 * @return array
	 */
	public function getInitialData(Request $request, int $company_id, int $timezone_offset_minutes) : array {
		
		$invoice_settings = new InvoiceSettingsService((int) $company_id);

		$custom_fields = $this->fetchInvoiceCustomFields($request);

		/* get payment integration data */
		$gateways = $this->getGateWayNames((int) $company_id);


		return [
			'invoice_number'	=>	(new HandleInvoiceNumbers((int) $company_id, $invoice_settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->getNextInvoiceNumber(),
			'product_columns' 	=> 	$invoice_settings->getProductColumns(),
			'total_fields' 		=> 	$invoice_settings->getTotalFields(),
			'custom_fields'		=>	$custom_fields['data_fields'],
			'gateways'			=>	$gateways
		];
		
	}

	/**
	 * fetchInvoiceCustomFields function
	 *
	 * @param Request $request
	 * @return array
	 */
	private function fetchInvoiceCustomFields(Request $request) : array{

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = InvoicesCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();

		return 	[
					'data_fields' 	=> $this->adjustRowsPrinting($fields),
				];
	}

}