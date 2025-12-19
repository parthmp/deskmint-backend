<?php

namespace App\Modules\InvoiceGeneration;

use Carbon\Carbon;

class InvoiceRenderer{

	private string $contents;
	private array $context;
	private int $time_offset_minutes;

	/**
	 * __construct function
	 *
	 * @param string $contents
	 * @param array $context
	 * @param integer $time_offset_minutes
	 */
	public function __construct(string $contents, array $context, int $time_offset_minutes = 0){
		$this->contents = $contents;
		$this->context = $context;
		$this->time_offset_minutes = $time_offset_minutes;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $date
	 * @param boolean $show_time
	 * @return string
	 */
	private function formatDateTime(string $date, bool $show_time = false) : string {

		$date_obj = Carbon::parse($date);

		if($this->time_offset_minutes < 0){
			$date_obj->subMinutes(abs($this->time_offset_minutes));	
		}else if($this->time_offset_minutes > 0){
			$date_obj->addMinutes(abs($this->time_offset_minutes));	
		}

		return $show_time ? $date_obj->format('d-M-Y H:i:s') : $date_obj->format('d-M-Y');

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
	 * renderClientDetails function
	 *
	 * @return InvoiceRenderer
	 */
	private function renderClientDetails() : InvoiceRenderer {

		$client_details_html = '';

		foreach($this->context['client_details_settings'] as $field){

			$mapped = $field['mapped'];
			
			if($field['type'] === 'normal'){
				
				$client_details_html .= '<p>';

				if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'gst/tax #' || strtolower($field['text']) === 'website'){
					$client_details_html .= $field['text'].' :';
				}else{
					$client_details_html .= ' ';
				}
				
				foreach($mapped as $mapped_field){
					
					if(strtolower($field['text']) === 'country'){
						$client_details_html .= ' '.$this->context['invoice_data']->client_wt->billing_country->country_name;
					}else{
						$client_details_html .= ' '.$this->context['invoice_data']->client_wt[$mapped_field];
					}
					
				}

				$client_details_html .= '</p>';

			}else{
				
				$client_details_html .= '<p>'.$field['text'].' :';

				foreach($this->context['client_custom_fields_values'] as $custom_field_value){
					
					if($field['clients_custom_field_id'] === $custom_field_value->clients_custom_field_id){
						
						$input_type = $custom_field_value->clients_custom_field_wt->custom_field_type_wt->input_type;

						$client_details_html .= match($input_type){
							config('global.field_types')[5] => $this->formatDateTime($custom_field_value->field_value), /* date */
							config('global.field_types')[7] => $this->formatDateTime($custom_field_value->field_value, true), /* datetime */
							config('global.field_types')[9] => $this->formatMultiSelectValues($custom_field_value->field_value), /* multiselect */
							default							=> $custom_field_value->field_value
						};

					}

				}
				$client_details_html .= '</p>';
			}

		}

		$this->contents = str_ireplace('{{$render_client_details}}', $client_details_html, $this->contents);

		return $this;

	}

	/**
	 * renderCompanyDetails function
	 *
	 * @return InvoiceRenderer
	 */
	private function renderCompanyDetails() : InvoiceRenderer {

		$company_details_html = '';
		
		foreach($this->context['company_details_settings'] as $field){
			
			$mapped = $field['mapped'];

			if($field['type'] === 'normal'){

				$company_details_html .= '<p>';

				if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'GST - VAT number'){
					$company_details_html .= $field['text'].' :';
				}else{
					$company_details_html .= ' ';
				}
				
				foreach($mapped as $mapped_field){
					$company_details_html .= ' '.$this->context['invoice_data']->company_wt[$mapped_field];
				}

				$company_details_html .= '</p>';

			}else{
				
				/**
				 * process additional custom company fields here.
				 */
				$company_details_html .= '<p>';

				foreach($this->context['additional_company_fields'] as $additional_company_field){
					if($field['id_column'] === $additional_company_field->id){
						$company_details_html .= $field['text'].' : ';
						$company_details_html .= $additional_company_field->value;
					}
				}

				$company_details_html .= '</p>';

			}

			

		}

		$this->contents = str_ireplace('{{$render_company_details}}', $company_details_html, $this->contents);

		return $this;

	}

	// private function renderInvoiceDetails(){

	// 	$invoice_details_html = '';

	// 	foreach($this->context['invoice_details_settings'] as $field){

	// 	}

	// 	$this->contents = str_ireplace('{{$render_invoice_details}}', $invoice_details_html, $this->contents);

	// }

	public function render(){
		$this->renderClientDetails()->renderCompanyDetails();
		return $this->contents;
	}

}