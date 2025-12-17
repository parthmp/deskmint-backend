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
	private function formatMultiSelectValues(string $values) : mixed{
		$json_values = json_decode($values, true);
		return str_ireplace("\n", ', ', $json_values[0]);
	}

	public function renderClientDetails(){

		$client_details_html = '';

		foreach($this->context['client_details'] as $field){

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
						$client_details_html .= ' '.$this->context['invoice_data']->client->billing_country->country_name;
					}else{
						$client_details_html .= ' '.$this->context['invoice_data']->client[$mapped_field];
					}
					
				}
				$client_details_html .= '</p>';
			}else{
				$client_details_html .= '<p>'.$field['text'].' :';
				foreach($this->context['client_custom_fields_values'] as $custom_field_value){
					if($field['clients_custom_field_id'] === $custom_field_value->clients_custom_field_id){
						
						$input_type = $custom_field_value->ClientsCustomField->customFieldType->input_type;
						
						if($input_type === config('global.field_types')[5]){ /* date */
							$client_details_html .= $this->formatDateTime($custom_field_value->field_value);
						}else if($input_type === config('global.field_types')[7]){ /* datetime */
							$client_details_html .= $this->formatDateTime($custom_field_value->field_value, true);
						}else if($input_type === config('global.field_types')[9]){ /* multiselect */
							$client_details_html .= $this->formatMultiSelectValues($custom_field_value->field_value);
						}else{
							$client_details_html .= $custom_field_value->field_value;
						}

					}
				}
				$client_details_html .= '</p>';
			}

		}

		$this->contents = str_ireplace('{{$render_client_details}}', $client_details_html, $this->contents);

	}

	public function render(){
		$this->renderClientDetails();
		return $this->contents;
	}

}