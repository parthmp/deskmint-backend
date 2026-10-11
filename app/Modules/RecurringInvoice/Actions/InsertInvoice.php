<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\RecurringInvoice\DB\DB;
use App\Services\HandleInvoiceNumbers;
use Illuminate\Support\Str;

class InsertInvoice {
	
	public function __construct(
		private DB $db
	){}

	/**
	 * insertInvoice function
	 *
	 * @param array $invoice_data
	 * @param array $column_settings
	 * @return array
	 */
	public function insertInvoice(array $invoice_data, array $column_settings) : array {

		
		$invoices = [];

		$column_settings = json_encode($column_settings);

		foreach($invoice_data as $entry){

			$status = InvoiceStatus::SENT->value;
			if((bool) $entry['mark_paid_automatically']){
				$status = InvoiceStatus::PAID->value;
			}

			$invoices[] = [
				'uuid'								=>	Str::uuid()->toString(),
				'recurring_invoice_id'				=>	$entry['id'],
				'client_id'							=>	$entry['client_id'],
				'first_name'						=>	$entry['first_name'],
				'last_name'							=>	$entry['last_name'],
				'full_name'							=>	$entry['first_name'].' '.$entry['last_name'],
				'client_company'					=>	$entry['client_company_name'],
				'currency_id'						=>	$entry['currency_id'],
				'company_id'						=>	$entry['company_id'],
				'invoice_number'					=>	'', //TODO: this will need modifications inside HandleInvoiceNumbers to generate multiple invoice numbers at once.
				'invoice_date'						=>	now(),
				'due_date'							=>	now(),
				'po_number'							=>	$entry['po_number'],
				'discount'							=>	$entry['discount'],
				'discount_type'						=>	$entry['discount_type'],
				'discount_amount_post_tax'			=>	$entry['discount_amount_post_tax'],
				'discount_amount_pre_tax'			=>	$entry['discount_amount_pre_tax'],
				'subtotal'							=>	$entry['subtotal'],
				'tax_amount'						=>	$entry['tax_amount'],
				'balance_due'						=>	(bool) $entry['mark_paid_automatically'] ? 0 : $entry['total'],
				'total'								=>	$entry['total'],
				'refunded_amount'					=>	0,
				'status'							=>	$status,
				'is_archived'						=>	0,
				'pdf_file'							=>	'',
				'xml_file'							=>	'',
				'invoice_terms'						=>	$entry['invoice_terms'],
				'payment_gateway'					=>	$entry['payment_gateway'],
				'pattern_matched'					=>	0, //TODO: handle this while working on invoice numbers for this,
				'scan_chars'						=>	0, //TODO: handle this while working on invoice numbers for this,
				'timezone'							=>	$entry['timezone'],
				'settings_snapshot'					=>	$column_settings,
				'reminders_sent'					=>	0,
				'last_reminder_sent_at'				=>	null,
				'sent_at'							=>	null,
				'hidden_sent_at'					=>	now(),
				'deleted_at'						=>	null,
				'created_at'						=>	now(),
				'updated_at'						=>	now()
			];
		}

		return $this->db->insertMultipleInvoices($invoices);

	}

}