<?php

namespace App\Modules\RecurringInvoice\Actions;

use App\Modules\RecurringInvoice\DB\DB;
use Illuminate\Support\Facades\Schema;

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
	 * assembleFlatTableData function
	 *
	 * @param array $inserted
	 * @param array $recurring_flat_table_values
	 * @return array
	 */
	private function assembleFlatTableData(array $inserted, array $recurring_flat_table_values) : array {

		if(!Schema::hasTable('recurring_invoices_flat')){
			return [];
		}
		
		$insert = [];
		$remove_columns = ['created_at', 'updated_at', 'id', 'invoice_id'];
		$columns_raw = Schema::getColumnListing('invoices_flat');
		$columns = array_values(array_diff($columns_raw, $remove_columns));
		
		foreach($inserted as $invoice_entry){

			$temp = [];

			$temp[$remove_columns[0]] = now();
			$temp[$remove_columns[1]] = now();

			$temp['invoice_id']	= $invoice_entry['id'];

			foreach($recurring_flat_table_values as $flat_table_value_entry){

				if((int) $invoice_entry['recurring_invoice_id'] === (int) $flat_table_value_entry['recurring_invoice_id']){
					foreach($columns as $column){
						$temp[$column] = $flat_table_value_entry[$column];
					}
				}

			}
			
			
			$insert[] = $temp;

		}

		return $insert;

	}

	/**
	 * insertCustomFields function
	 *
	 * @param array $inserted
	 * @param array $custom_field_values
	 * @param array $recurring_flat_table_values
	 * @return void
	 */
	public function insertCustomFields(array $inserted, array $custom_field_values, array $recurring_flat_table_values) : void {

		$rows = $this->assembleData($inserted, $custom_field_values);

		if(!empty($rows)){
			$this->db->insertCustomFieldValues($rows);
		}

		$flat_table_rows = $this->assembleFlatTableData($inserted, $recurring_flat_table_values);

		if(!empty($flat_table_rows)){
			$this->db->insertFlatTableData($flat_table_rows);
		}
		

	}



}