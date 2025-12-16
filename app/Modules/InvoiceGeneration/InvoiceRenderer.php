<?php

namespace App\Modules\InvoiceGeneration;

class InvoiceRenderer{

	private string $contents;
	private array $context;

	public function __construct(string $contents, array $context){
		$this->contents = $contents;
		$this->context = $context;
	}

	public function renderClientDetails(){
		// [{"id": 1, "text": "Name", "type": "normal", "value": "name", "mapped": ["first_name", "last_name"]}, {"id": 2, "text": "GST/TAX #", "type": "normal", "value": "gst_tax", "mapped": ["tax_number"]}, {"id": 3, "text": "Street", "type": "normal", "value": "street", "mapped": ["billing_street"]}, {"id": 4, "text": "Apt/Suite", "type": "normal", "value": "apt_suite", "mapped": ["billing_apt"]}, {"id": 5, "text": "City - State - Postal", "type": "normal", "value": "city___state___postal", "mapped": ["billing_city", "billing_state", "billing_postal_code"]}, {"id": 6, "text": "Country", "type": "normal", "value": "country", "mapped": ["billing_country_id"]}, {"id": 13, "text": "Client DateTime", "type": "custom", "value": "client_datetime", "mapped": null, "clients_custom_field_id": 1}, {"id": 7, "text": "Phone", "type": "normal", "value": "phone", "mapped": ["phone"]}, {"id": 8, "text": "Email", "type": "normal", "value": "email", "mapped": ["email"]}, {"id": 14, "text": "Postal City", "type": "normal", "value": "postal_city", "mapped": ["shipping_city"]}, {"id": 15, "text": "Postal City - State", "type": "normal", "value": "postal_city___state", "mapped": ["shipping_city", "shipping_state"]}, {"id": 16, "text": "Website", "type": "normal", "value": "website", "mapped": ["website"]}]

		$client_details_html = '';

		foreach($this->context['client_details'] as $field){

			$mapped = $field['mapped'];
			
			if($field['type'] === 'normal'){
				$client_details_html .= '<p>';
				foreach($mapped as $mapped_field){
					$client_details_html .= $this->context['invoice_data'][$mapped_field];
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