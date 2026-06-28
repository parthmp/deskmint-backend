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
	private array $data;
	
	/**
	 * __construct function
	 *
	 * @param string $contents
	 * @param array $data
	 */
	public function __construct(string $contents, array $data){
		$this->contents = $contents;
		$this->data = $data;
	}

	/**
	 * renderClientDetails function
	 *
	 * @return InvoiceRenderer
	 */
	private function renderClientDetails() : InvoiceRenderer {

		$client_details_html = '';

		foreach($this->data['client'] as $field){

			$client_details_html .= '<p class="'.strtolower(str_ireplace('#', 'number', str_ireplace(' ', '_', $field['text']))).'">';

			if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'gst/tax #' || strtolower($field['text']) === 'website'){
				$client_details_html .= $field['text'].' : '.$field['value'];
			}else{
				$client_details_html .= ' '.$field['value'];
			}

			$client_details_html .= '</p>';
			
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

		foreach($this->data['company'] as $field){

			$company_details_html .= '<p class="'.strtolower(str_ireplace('#', 'number', str_ireplace(' ', '_', $field['text']))).'">';

			if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'GST - VAT number'){
				$company_details_html .= $field['text'].' : '.$field['value'];
			}else{
				$company_details_html .= ' '.$field['value'];
			}

			$company_details_html .= '</p>';

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

		foreach($this->data['invoice'] as $field){

			$invoice_details_html .= '<p class="'.strtolower(str_ireplace('#', 'number', str_ireplace(' ', '_', $field['text']))).'">';

			if(strtolower($field['text']) === 'phone' || strtolower($field['text']) === 'gst/tax #' || strtolower($field['text']) === 'website'){
				$invoice_details_html .= $field['text'].' : '.$field['value'];
			}else{
				$invoice_details_html .= ' '.$field['value'];
			}

			$lowercase_field_text = trim(strtolower($field['text']));
			if($lowercase_field_text === 'total' || $lowercase_field_text === 'balance due'){
				$invoice_details_html .= ' '.$this->data['meta']['currency'];
			}

			$invoice_details_html .= '</p>';

		}

		$this->contents = str_ireplace('{{$render_invoice_details}}', $invoice_details_html, $this->contents);

		return $this;

	}

	private function renderProductRows() : self {

		$product_columns_html = '<thead>';
		$product_columns_html = '<tr>';

		foreach($this->data['product_rows']['headers'] as $header){
			$product_columns_html .= '<th>';
			$product_columns_html .= $header['text'];
			$product_columns_html .= '</th>';
		}

		$product_columns_html = '</tr>';
		$product_columns_html = '</thead>';

		$product_columns_html .= '<tbody>';
		foreach($this->data['product_rows']['data'] as $row){

			$product_columns_html .= '<tr>';

			foreach($row as $row_line){
				$product_columns_html .= '<td>';
				$product_columns_html .= $row_line;
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

		$total_fields = '';

		foreach($this->data['totals'] as $field){

			$total_fields .= '<p>';
			
			$total_fields .= $field['text'].' : '.$field['value'].' '.$this->data['meta']['currency'];

			$total_fields .= '</p>';

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
		$terms .= $this->data['terms']['company_terms'];
		$terms .= '</p>';
		
		$terms .= '<p><strong>Invoice terms';
		$terms .= '</strong><br>';
		$terms .= nl2br($this->data['terms']['invoice_terms']);
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

		$footer = $this->data['terms']['footer'];

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
			$storage_path = storage_path('app/public/logos/'.$this->data['meta']['company_id'].'/'.$this->data['logo']);
			$encoded = base64_encode(file_get_contents($storage_path));
			$mime = mime_content_type($storage_path);
			$logo = '<img width="'.$this->data['general']['logo_size'].'" src="data:'.$mime.';base64, '.$encoded.'">';
			
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

		
		$primary = $this->data['general']['primary_color'];	

		$secondary = $this->data['general']['secondary_color'];

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

		
		$font = (int) $this->data['general']['font_size'];
			
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