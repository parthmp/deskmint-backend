<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Models\SettingsSection;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Modules\RecurringInvoice\DB\DB;
use App\Services\HandleInvoiceNumbers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class InsertInvoice {
	
	public function __construct(
		private DB $db
	){}

	/**
	 * groupEntriesByTimezone function
	 *
	 * @param array $invoice_data
	 * @return array
	 */
	private function groupEntriesByTimezone(array $invoice_data) : array {

		$grouped_data = [];

		foreach($invoice_data as $entry){
			
			$timezone_string = str_ireplace('/', '_', strtolower($entry['timezone']));
			$timezone_string = str_ireplace('+', '__', $timezone_string);
			$timezone_string = str_ireplace('-', '_', $timezone_string);
			$timezone_string = str_ireplace(' ', '_', $timezone_string);

			if(!isset($grouped_data[$timezone_string])){
				$grouped_data[$timezone_string] = [];
			}

			$grouped_data[$timezone_string][] = $entry;

		}

		return $grouped_data;

	}

	/**
	 * generateFinalData function
	 *
	 * @param array $grouped_data
	 * @param array $invoice_numbers_settings
	 * @param SettingsSection|null $manual_reset
	 * @return array
	 */
	private function generateFinalData(array $grouped_data, array $invoice_numbers_settings, ?SettingsSection $manual_reset) : array {

		$last_number = null;
		$final_invoice_data = [];

		foreach($grouped_data as $group){

			$timezone = $group[0]['timezone'];

			$handle_invoice_number = new HandleInvoiceNumbers((int) $group[0]['company_id'], $invoice_numbers_settings, $timezone);

			$invoice_numbers = $handle_invoice_number->setManualReset($manual_reset)->getNextInvoiceNumber((int) count($group), $last_number);
			if(!is_array($invoice_numbers)){
				$invoice_numbers = [$invoice_numbers];	
			}
			
			$last_number = end($invoice_numbers);
			
			//weave
			for($z = 0 ; $z < count($group) ; $z++){
				$group[$z]['invoice_number'] = $invoice_numbers[$z];
				$patten_result = $handle_invoice_number->checkPatternWithSuffix($invoice_numbers[$z]);
				$group[$z]['scan_chars'] = strlen((string) $patten_result['suffix']);
				$final_invoice_data[] = $group[$z];
			}

		}

		return $final_invoice_data;
	}

	/**
	 * insertInvoice function
	 *
	 * @param array $invoice_data
	 * @param array $column_settings
	 * @param array $invoice_numbers_settings
	 * @param array|null $online_transaction_data
	 * @return array
	 */
	public function insertInvoice(array $invoice_data, array $column_settings, array $invoice_numbers_settings, ?array $online_transaction_data = null) : array {

		$invoices = [];

		$column_settings = json_encode($column_settings);

		$manual_reset = null;
		if(isset($invoice_data[0]['company_id'])){
			$manual_reset = $this->db->fetchManualReset((int) $invoice_data[0]['company_id']);
		}
		
		$grouped_data = $this->groupEntriesByTimezone($invoice_data);

		
		$final_invoice_data = $this->generateFinalData($grouped_data, $invoice_numbers_settings, $manual_reset);
		
		foreach($final_invoice_data as $entry){

			$status = InvoiceStatus::SENT->value;
			$balance_due = $entry['total'];
			if((bool) $entry['mark_paid_automatically'] && (int) $entry['payment_gateway'] === PaymentGateway::NONE->value){
				$status = InvoiceStatus::PAID->value;
				$balance_due = 0;
			}else if((int) $entry['payment_gateway'] !== PaymentGateway::NONE->value){
				if($online_transaction_data){
					$online_data_recurring_ids = array_column($online_transaction_data, 'recurring_invoice_id');
					if(in_array($entry['id'], $online_data_recurring_ids)){
						$status = InvoiceStatus::PAID->value;
						$balance_due = 0;
					}
				}
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
				'invoice_number'					=>	$entry['invoice_number'],
				'invoice_date'						=>	now(),
				'due_date'							=>	now(),
				'po_number'							=>	$entry['po_number'],
				'discount'							=>	$entry['discount'],
				'discount_type'						=>	$entry['discount_type'],
				'discount_amount_post_tax'			=>	$entry['discount_amount_post_tax'],
				'discount_amount_pre_tax'			=>	$entry['discount_amount_pre_tax'],
				'subtotal'							=>	$entry['subtotal'],
				'tax_amount'						=>	$entry['tax_amount'],
				'balance_due'						=>	$balance_due,
				'total'								=>	$entry['total'],
				'refunded_amount'					=>	0,
				'status'							=>	$status,
				'is_archived'						=>	0,
				'pdf_file'							=>	'',
				'xml_file'							=>	'',
				'invoice_terms'						=>	$entry['invoice_terms'],
				'payment_gateway'					=>	$entry['payment_gateway'],
				'pattern_matched'					=>	1,
				'scan_chars'						=>	$entry['scan_chars'],
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

		$res = $this->db->insertMultipleInvoices($invoices);		
		return $res;

	}

}