<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Modules\RecurringInvoice\DB\DB;
use Illuminate\Support\Str;

class InsertInvoiceItems {

	public function __construct(
		private DB $db
	){}
	
	/**
	 * assembleData function
	 *
	 * @param array $inserted
	 * @param array $recurring_invoice_items
	 * @return array
	 */
	private function assembleData(array $inserted, array $recurring_invoice_items) : array {

		$insert = [];

		foreach($inserted as $inserted_entry){

			foreach($recurring_invoice_items as $item){

				if((int) $inserted_entry['recurring_invoice_id'] === (int) $item['recurring_invoice_id']){

					$insert[] = [
						'row_uuid'				=>	Str::uuid()->toString(),
						'invoice_id'			=>	$inserted_entry['id'],
						'product_id'			=>	$item['product_id'],
						'description'			=>	$item['description'],
						'unit_price'			=>	$item['unit_price'],
						'quantity'				=>	$item['quantity'],
						'discount'				=>	$item['discount'],
						'discount_amount'		=>	$item['discount_amount'],
						'tax'					=>	$item['tax'],
						'tax_amount'			=>	$item['tax_amount'],
						'line_total'			=>	$item['line_total'],
						'line_subtotal'			=>	$item['line_subtotal'],
						'created_at'			=>	now(),
						'updated_at'			=>	now(),
					];

				}

			}

		}

		return $insert;
	}

	/**
	 * insertInvoiceItems function
	 *
	 * @param array $inserted
	 * @param array $recurring_invoice_items
	 * @return void
	 */
	public function insertInvoiceItems(array $inserted, array $recurring_invoice_items) : void {
		
		//$inserted has id and recurring_invoice_id both, where id means inserted id from invoices table and recurring_invoice_id from invoices table.
		$rows = $this->assembleData($inserted, $recurring_invoice_items);
		$this->db->insertInvoiceItems($rows);

	}

}