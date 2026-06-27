<?php

namespace App\Modules\InvoiceGeneration;

use App\Helpers\General;
use App\Models\Invoice;
use App\Modules\InvoiceGeneration\InvoiceSettingsResolver;
use App\Repositories\Invoice\InvoiceRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class InvoiceSnapshot {

	private array $snapshot = [
		'logo'					=>	'',
		'general'				=>	[],
		'client'				=>	[],
		'company'				=>	[],
		'invoice'				=>	[],
		'product_rows'			=>	[],
		'product_rows_settings'	=>	[],
		'totals'				=>	[],
		'terms'					=>	[],
		'meta'					=>	[]
	];

	private int $company_id;
	private int $invoice_id;
	private Invoice $invoice;
	private int $timezone_offset_minutes;

	public function __construct(
		private InvoiceRepository $invoice_repository,
		private InvoiceSettingsResolver $invoice_settings_resolver,
		private InvoiceDBOperations $invoice_db_operations
	){}

	/**
	 * setCompanyId function
	 *
	 * @param integer $company_id
	 * @return self
	 */
	public function setCompanyId(int $company_id) : self {
		$this->company_id = $company_id;
		$this->invoice_settings_resolver = $this->invoice_settings_resolver->setCompanyId($company_id);
		return $this;
	}

	/**
	 * setInvoiceId function
	 *
	 * @param integer $invoice_id
	 * @return self
	 */
	public function setInvoiceId(int $invoice_id) : self {
		$this->invoice_id = $invoice_id;
		$this->invoice_settings_resolver = $this->invoice_settings_resolver->setInvoiceId($this->invoice_id);
		$this->invoice_db_operations = $this->invoice_db_operations->setCompanyId($this->company_id)->setInvoiceId($this->invoice_id)->execRequiredSettings();
		$this->invoice = $this->invoice_db_operations->fetchInvoiceRow();
		return $this;
	}

	/**
	 * setTimezoneOffset function
	 *
	 * @param integer $timezone_offset_minutes
	 * @return self
	 */
	public function setTimezoneOffset(int $timezone_offset_minutes) : self {
		$this->timezone_offset_minutes = $timezone_offset_minutes;
		return $this;
	}

	/**
	 * setLogoSnapsot function
	 *
	 * @return self
	 */
	public function setLogoSnapsot() : self {
		$this->snapshot['logo'] = $this->invoice_repository->fetchLogoImage($this->company_id);
		return $this;
	}

	/**
	 * setGeneralSettings function
	 *
	 * @return self
	 */
	public function setGeneralSettings() : self {
		$this->snapshot['general'] = $this->invoice_settings_resolver->fetchGeneral();
		return $this;
	}

	/**
	 * formatMultiSelectValues function
	 *
	 * @param string $values
	 * @return string
	 */
	private function formatMultiSelectValues(string $values) : string {
		$json_values = json_decode($values, true);
		return str_ireplace("\n", ', ', $json_values[0]);
	}

	/**
	 * parseCustomFields function
	 *
	 * @param Collection $field_values
	 * @param array $field
	 * @param string $type
	 * @return array
	 */
	private function parseCustomFields(Collection $field_values, array $field, string $type) : array {

		$content = [
			'text'	=>	$field['text'],
			'value'	=>	'',
		];


		foreach($field_values as $custom_field_value){
			
			if((int) $field[$type.'s_custom_field_id'] === (int) $custom_field_value->{$type.'s_custom_field_id'}){
				
				$input_type = $custom_field_value->{$type.'s_custom_field_wt'}->custom_field_type_wt->input_type;

				$content['value'] .= match($input_type){
					config('global.field_types')[5] => General::formatDateTime($custom_field_value->field_value, $this->timezone_offset_minutes), /* date */
					config('global.field_types')[7] => General::formatDateTime($custom_field_value->field_value, $this->timezone_offset_minutes, true), /* datetime */
					config('global.field_types')[9] => $this->formatMultiSelectValues($custom_field_value->field_value), /* multiselect */
					default							=> $custom_field_value->field_value
				};

			}

		}
		

		return $content;

	}

	/**
	 * setClientSnapshot function
	 *
	 * @return self
	 */
	public function setClientSnapshot() : self {
		
		$client_details = $this->invoice_settings_resolver->fetchClientDetails();
		$client_custom_fields_values = $this->invoice_db_operations->fetchCustomFieldValuesOfClient((int) $this->invoice->client_id);

		if(isset($client_details['rows'])){
			$client_details = $client_details['rows'];
		}

		//weave
		$client_details_with_values = [];
		
		foreach($client_details as $field){

			$temp = [
				'text'	=>	'',
				'value'	=>	'',
			];

			if(isset($field['mapped'])){
				
				$mapped = $field['mapped'];
			
				if($field['type'] === 'normal'){
					
					$temp['text'] = strtolower(str_ireplace('#', 'number', str_ireplace(' ', ' ', $field['text'])));

					if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'gst/tax #' || strtolower($field['text']) === 'website'){
						$temp['text'] = $field['text'];
					}
					
					foreach($mapped as $mapped_field){
						
						if(strtolower($field['text']) === 'country'){
							$temp['value'] = $this->invoice->client_wt->billing_country->country_name;
						}else{
							$temp['value'] = $this->invoice->client_wt[$mapped_field];
						}
						
					}

					$client_details_with_values[] = $temp;

				}else{

					$client_details_with_values[] = $this->parseCustomFields($client_custom_fields_values, $field, 'client');

				}
			}
			

		}

		$this->snapshot['client'] = $client_details_with_values;
		$client_details_with_values = null;

		return $this;
	}

	/**
	 * setCompanySnapshot function
	 *
	 * @return self
	 */
	public function setCompanySnapshot() : self {
		
		$company_details = $this->invoice_settings_resolver->fetchCompanyDetails();
		$additional_fields = $this->invoice_db_operations->fetchAdditionalCompanyFields();
		
		//weave
		$company_details_with_values = [];
		
		foreach($company_details as $field){

			$temp = [
				'text'	=>	'',
				'value'	=>	'',
			];
			
			$mapped = $field['mapped'];

			if($field['type'] === 'normal'){

				$temp['text'] = strtolower(str_ireplace('#', 'number', $field['text']));

				if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'GST - VAT number'){
					$temp['text'] = $field['text'];
				}
				
				foreach($mapped as $mapped_field){
					$temp['value'] = $this->invoice->company_wt[$mapped_field];
				}

				$company_details_with_values[] = $temp;

			}else{
				
				/**
				 * process additional custom company fields here.
				 */
				
				foreach($additional_fields as $additional_company_field){
					if((int) $field['id_column'] === (int) $additional_company_field->id){
						$temp['text'] = $field['text'];
						$temp['value'] = $additional_company_field->value;
						$company_details_with_values[] = $temp;
					}
				}

				
				
			}

			

		}

		$this->snapshot['company'] = $company_details_with_values;
		$company_details_with_values = null;

		return $this;
	}

	/**
	 * setInvoiceSnapshot function
	 *
	 * @return self
	 */
	public function setInvoiceSnapshot() : self {

		$invoice_details = $this->invoice_settings_resolver->fetchInvoiceDetails();
		$invoice_custom_fields = $this->invoice_db_operations->fetchCustomFieldValuesOfInvoice() ?? [];
	
		$invoice_details_with_values = [];
		
		foreach($invoice_details as $field){

			$temp = [
				'text'	=>	'',
				'value'	=>	'',
			];

			$mapped = $field['mapped'];
			
			if($field['type'] === 'normal'){
				
				$temp['text'] = strtolower(str_ireplace('#', 'number', $field['text']));

				if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'gst/tax #' || strtolower($field['text']) === 'website'){
					$temp['text'] = $field['text'];
				}
				
				foreach($mapped as $mapped_field){

					$temp['text'] = $field['text'];
					$temp['value'] = $this->invoice[$mapped_field];

					if(Carbon::canBeCreatedFromFormat($temp['value'], 'Y-m-d') || Carbon::canBeCreatedFromFormat($temp['value'], 'Y-m-d H:i:s')){
						$temp['value'] = General::formatDateTime($temp['value'], $this->timezone_offset_minutes, false, false);
					}
					
					$invoice_details_with_values[] = $temp;
				}

			}else{
				$invoice_details_with_values[] = $this->parseCustomFields($invoice_custom_fields, $field, 'invoice');
			}

		}

		$this->snapshot['invoice'] = $invoice_details_with_values;
		$invoice_details_with_values = null;


		return $this;
	}

	/**
	 * setInvoiceRowsSnapshot function
	 *
	 * @return self
	 */
	public function setInvoiceRowsSnapshot() : self {

		$context['product_rows_data'] = $this->invoice_settings_resolver->fetchProductRowsSettings($context['invoice_data']);
		$context['invoice_items'] = $this->invoice_db_operations->fetchInvoiceItemsWithCustomCols();
		
		return $this;
	}

	public function output() : array {

		return $this->snapshot;

	}


}