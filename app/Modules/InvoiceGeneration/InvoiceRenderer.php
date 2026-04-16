<?php

namespace App\Modules\InvoiceGeneration;

use App\Repositories\Company\CompanyRepository;
use App\Services\CompanySettingsLogo\CompanySettingsLogoService;
use Carbon\Carbon;

class InvoiceRenderer{

	private string $contents;
	private array $context;
	private int $time_offset_minutes;
	private CompanySettingsLogoService $company_settings_logo_service;

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
		$this->company_settings_logo_service = new CompanySettingsLogoService(new CompanyRepository());
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
	 * renderCustomFields function
	 *
	 * @param array $field
	 * @param string $type
	 * @return string
	 */
	private function renderCustomFields(array $field, string $type) : string{

		$content = '<p>'.$field['text'].' :';

		foreach($this->context[$type.'_custom_fields_values'] as $custom_field_value){
			
			if($field[$type.'s_custom_field_id'] === $custom_field_value->{$type.'s_custom_field_id'}){
				
				$input_type = $custom_field_value->{$type.'s_custom_field_wt'}->custom_field_type_wt->input_type;

				$content .= match($input_type){
					config('global.field_types')[5] => $this->formatDateTime($custom_field_value->field_value), /* date */
					config('global.field_types')[7] => $this->formatDateTime($custom_field_value->field_value, true), /* datetime */
					config('global.field_types')[9] => $this->formatMultiSelectValues($custom_field_value->field_value), /* multiselect */
					default							=> $custom_field_value->field_value
				};

			}

		}
		$content .= '</p>';

		return $content;

	}

	/**
	 * renderClientDetails function
	 *
	 * @return InvoiceRenderer
	 */
	private function renderClientDetails() : InvoiceRenderer {

		$client_details_html = '';

		foreach($this->context['client_details_settings'] as $field){

			if(isset($field['mapped'])){
				
				$mapped = $field['mapped'];
			
				if($field['type'] === 'normal'){
					
					$client_details_html .= '<p class="'.strtolower(str_ireplace('#', 'number', str_ireplace(' ', '_', $field['text']))).'">';

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

					$client_details_html .= $this->renderCustomFields($field, 'client');

				}
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

				$company_details_html .= '<p class="'.strtolower(str_ireplace('#', 'number', str_ireplace(' ', '_', $field['text']))).'">';

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
					if((int) $field['id_column'] === (int) $additional_company_field->id){
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

	/**
	 * renderInvoiceDetails function
	 *
	 * @return InvoiceRenderer
	 */
	private function renderInvoiceDetails() : InvoiceRenderer {

		$invoice_details_html = '';
		
		foreach($this->context['invoice_details_settings'] as $field){

			$mapped = $field['mapped'];
			
			if($field['type'] === 'normal'){
				
				$invoice_details_html .= '<p class="'.strtolower(str_ireplace('#', 'number', str_ireplace(' ', '_', $field['text']))).'">';

				if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'gst/tax #' || strtolower($field['text']) === 'website'){
					$invoice_details_html .= $field['text'].' :';
				}else{
					$invoice_details_html .= ' ';
				}
				
				foreach($mapped as $mapped_field){
					$invoice_details_html .= $field['text'].' : '.$this->context['invoice_data'][$mapped_field];
				}
				
				
				$lowercase_field_text = trim(strtolower($field['text']));
				if($lowercase_field_text === 'total' || $lowercase_field_text === 'balance due'){
					$invoice_details_html .= ' '.$this->context['invoice_data']->client_wt->currency->code;
				}

				$invoice_details_html .= '</p>';

			}else{
				$invoice_details_html .= $this->renderCustomFields($field, 'invoice');
			}

		}

		$this->contents = str_ireplace('{{$render_invoice_details}}', $invoice_details_html, $this->contents);

		return $this;

	}

	private function renderProductRows() : self {

		$product_columns_html = '<thead>';

		//generate headers for the product rows table
		$product_columns_html = '<tr>';
		
		foreach($this->context['product_rows_data'] as $row){
			$product_columns_html .= '<th>';
			$product_columns_html .= $row['text'];
			$product_columns_html .= '</th>';
		}

		$product_columns_html .= '</tr>';
		$product_columns_html .= '</thead>';

		$product_columns_html .= '<tbody>';

		foreach($this->context['invoice_items'] as $item){
			
			$product_columns_html .= '<tr>';
			

			foreach($this->context['product_rows_data'] as $row){

				$product_columns_html .= '<td>';

				if($row['type'] === 'normal'){
					$mapped = $row['mapped'];
					if($mapped[0] === 'tax'){
						$product_columns_html .= (double) $item->tax.'%';
					}else{
						if($mapped[0] === 'product_id'){
							$product_columns_html .= $item->product->product_name;
						}else{
							$product_columns_html .= $item->{$mapped[0]};
						}
						
					}

				}else{
					//for custom product row fields.
					if((int) $row['tax'] === 1){

						//for tax fields
						$product_columns_html .= (double) $row['tax_rate'].'%';

					}else{
						$product_columns_html .= $row['text'];
					}
				}	

				$product_columns_html .= '</td>';

			}
			

			$product_columns_html .= '</tr>';

		}

		$product_columns_html .= '</tbody>';

		$this->contents = str_ireplace('{{$render_product_rows}}', $product_columns_html, $this->contents);

		return $this;
	}

	/**
	 * renderTotals function
	 *
	 * @return self
	 */
	private function renderTotals() : self {

		$currency_code = $this->context['invoice_data']->client_wt->currency->code;

		$total_fields = '';

		foreach($this->context['total_fields_settings'] as $field){
			
			$mapped = $field['mapped'][0];
			$total_fields .= '<p>'.$field['text'].': '.(double) $this->context['invoice_data'][$mapped].' '.$currency_code.'</p>';

		}

		$this->contents = str_ireplace('{{$render_total_fields}}', $total_fields, $this->contents);

		return $this;
	}

	/**
	 * renderTerms function
	 *
	 * @return self
	 */
	private function renderTerms() : self {

		$terms = $this->context['invoice_data']->company->invoice_terms;

		$this->contents = str_ireplace('{{$render_terms}}', $terms, $this->contents);

		return $this;

	}

	/**
	 * renderFooter function
	 *
	 * @return self
	 */
	private function renderFooter() : self{

		$footer = $this->context['invoice_data']->company->invoice_footer;

		$this->contents = str_ireplace('{{$render_footer}}', $footer, $this->contents);

		return $this;
	}

	/**
	 * renderLogo function
	 *
	 * @return self
	 */
	private function renderLogo() : self {

		$logo = '<img width="'.$this->context['general_settings']['logo_size'].'" src="'.$this->company_settings_logo_service->fetch($this->context['invoice_data']->company->id).'">';

		$this->contents = str_ireplace('{{$render_logo}}', $logo, $this->contents);

		return $this;

	}

	/**
	 * modifyThemeColors function
	 *
	 * @return self
	 */
	private function modifyThemeColors() : self {

		$primary = '#1f2937';
		
		if(isset($this->context['general_settings']['primary_color'])){
			if(trim($this->context['general_settings']['primary_color']) !== ''){
				$primary = $this->context['general_settings']['primary_color'];
			}
		}

		$secondary = '#333';

		if(isset($this->context['general_settings']['secondary_color'])){
			if(trim($this->context['general_settings']['secondary_color']) !== ''){
				$secondary = $this->context['general_settings']['secondary_color'];
			}
		}

		$this->contents = str_ireplace('{{$render_primary_color}}', $primary, $this->contents);
		$this->contents = str_ireplace('{{$render_secondary_color}}', $secondary, $this->contents);
		return $this;
	}

	/**
	 * modifyFontSize function
	 *
	 * @return self
	 */
	private function modifyFontSize() : self {

		$font = 14;

		if(isset($this->context['general_settings']['font_size'])){
			if(trim($this->context['general_settings']['font_size']) !== ''){
				$font = (int) $this->context['general_settings']['font_size'];
			}
		}

		$font_inc = $font + 2;

		$this->contents = str_ireplace('{{$render_font_size}}', $font.'px', $this->contents);
		$this->contents = str_ireplace('{{$render_font_size_inc}}', $font_inc.'px', $this->contents);

		return $this;
	}

	public function render(){
		$this->renderLogo()->renderClientDetails()->renderCompanyDetails()->renderInvoiceDetails()->renderProductRows()->renderTotals()->renderTerms()->renderFooter()->modifyThemeColors()->modifyFontSize();
		return $this->contents;
	}

}