<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Modules\RecurringInvoice\DB\DB;

class InsertCustomFields {
	
	public function __construct(
		private DB $db
	){}

	/**
	 * assembleData function
	 *
	 * @param array $inserted
	 * @param array $custom_field_values
	 * @return array
	 */
	private function assembleData(array $inserted, array $custom_field_values) : array {

		$insert = [];

		foreach($inserted as $invoice_entry){

			foreach($custom_field_values as $field_value_entry){

				if((int) $invoice_entry['recurring_invoice_id'] === (int) $field_value_entry['recurring_invoice_id']){

					$insert[] = [
						'invoice_id'					=>	$invoice_entry['id'],
						'invoices_custom_field_id'		=>	$field_value_entry['recurring_invoices_custom_field_id'],
						'field_value'					=>	$field_value_entry['field_value'],
						'created_at'					=>	now(),
						'updated_at'					=>	now()
					];

				}

			}

		}

		return $insert;

	}

	/**
	 * insertCustomFields function
	 *
	 * @param array $inserted
	 * @param array $custom_field_values
	 * @return void
	 */
	public function insertCustomFields(array $inserted, array $custom_field_values) : void {

		$rows = $this->assembleData($inserted, $custom_field_values);
		$this->db->insertCustomFieldValues($rows);

	}

}