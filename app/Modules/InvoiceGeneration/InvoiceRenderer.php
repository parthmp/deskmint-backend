<?php

namespace App\Modules\InvoiceGeneration;

use App\Helpers\General;
use App\Repositories\Company\CompanyRepository;
use App\Services\CompanySettingsLogo\CompanySettingsLogoService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Exception;

use function Laravel\Prompts\number;

class InvoiceRenderer extends InvoiceGenerator{

	private string $contents;
	private array $context;
	private CompanySettingsLogoService $company_settings_logo_service;

	/**
	 * __construct function
	 *
	 * @param string $contents
	 * @param array $context
	 * @param integer $time_offset_minutes
	 */
	public function __construct(string $contents, array $context, int $time_offset_minutes = 0, ?CompanySettingsLogoService $company_settings_logo_service = null){
		$this->contents = $contents;
		$this->context = $context;
		$this->time_offset_minutes = $time_offset_minutes;
		$this->company_settings_logo_service = $company_settings_logo_service ?? new CompanySettingsLogoService(new CompanyRepository());
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
			
			if((int) $field[$type.'s_custom_field_id'] === (int) $custom_field_value->{$type.'s_custom_field_id'}){
				
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
						$product_columns_html .= number_format((float) $item->tax, 2).'%';
					}else{
						if($mapped[0] === 'product_id'){
							$product_columns_html .= $item->product->product_name;
						}else{
							$temp_value = $item->{$mapped[0]};
							if(trim(strtolower($mapped[0])) === 'unit_price'){
								$temp_value = number_format((float) $item->{$mapped[0]}, 2);
							}
							$product_columns_html .= $temp_value;
						}
						
					}

				}else{

					foreach($item->custom_field_values as $custom_field){
						if((string) $custom_field->row_uuid === (string) $item->row_uuid && (int) $custom_field->apc_field_id === (int) $row['id_column']){
							if((int) $row['tax'] === 1){
								$product_columns_html .= number_format((float) (($custom_field->value == '') ? 0 : $custom_field->value), 2).'%';
							}else{
								$product_columns_html .= $custom_field->value;
							}
						}
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
			
			if(trim(strtolower($field['text'])) === 'paid to date'){
				$math = BigDecimal::of($this->context['invoice_data']['total'])->minus($this->context['invoice_data']['balance_due']);
				$show_value = $math->toScale(2, RoundingMode::HalfUp)->__toString();
			}else{
				$mapped = $field['mapped'][0];
				$show_value = number_format((float) $this->context['invoice_data'][$mapped], 2);
			}
			
			$total_fields .= '<p>'.$field['text'].': '.$show_value.' '.$currency_code.'</p>';

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

		$terms = '<p><strong>Company terms';

		$terms .= '</strong><br>';
		$terms .= $this->context['invoice_data']->company->invoice_terms;
		$terms .= '</p>';
		
		$terms .= '<p><strong>Invoice terms';
		$terms .= '</strong><br>';
		$terms .= nl2br($this->context['invoice_data']->invoice_terms);
		$terms .= '</p>';
		

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

		try{
			$storage_path = storage_path('app/public/logos/'.$this->context['invoice_data']->company_id.'/'.$this->context['invoice_data']->company_wt->logo);
			$encoded = base64_encode(file_get_contents($storage_path));
			$mime = mime_content_type($storage_path);
			$logo = '<img width="'.$this->context['general_settings']['logo_size'].'" src="data:'.$mime.';base64, '.$encoded.'">';
			
			$this->contents = str_ireplace('{{$render_logo}}', $logo, $this->contents);
		}catch(Exception $e){
			$this->contents = str_ireplace('{{$render_logo}}', '', $this->contents);
		}
		

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