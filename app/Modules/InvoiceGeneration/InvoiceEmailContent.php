<?php

namespace App\Modules\InvoiceGeneration;

use App\Helpers\General;
use App\Models\Invoice;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class InvoiceEmailContent {

	private string $disk;
	private Invoice $invoice;
	private string $invoice_content;
	private ?string $alt_client_first_name = null;
	private ?string $alt_client_last_name = null;
	private ?string $alt_currency_code = null;

	/**
	 * setDisk function
	 *
	 * @param string $disk
	 * @return self
	 */
	public function setDisk(string $disk) : self {
		$this->disk = $disk;
		return $this;
	}

	/**
	 * setInvoice function
	 *
	 * @param Invoice $invoice
	 * @return self
	 */
	public function setInvoice(Invoice $invoice) : self {
		$this->invoice = $invoice;
		return $this;
	}

	/**
	 * setInvoiceContent function
	 *
	 * @param string $invoice_content
	 * @return self
	 */
	public function setInvoiceContent(string $invoice_content) : self {
		$this->invoice_content = $invoice_content;
		return $this;
	}

	/**
	 * setAltCurrencyCode function
	 *
	 * @param string $currency_code
	 * @return self
	 */
	public function setAltCurrencyCode(string $currency_code) : self {
		$this->alt_currency_code = $currency_code;
		return $this;
	}

	/**
	 * setAltClientFirstName function
	 *
	 * @param string $client_first_name
	 * @return self
	 */
	public function setAltClientFirstName(string $client_first_name) : self {
		$this->alt_client_first_name = $client_first_name;
		return $this;
	}

	/**
	 * setAltClientLastName function
	 *
	 * @param string $client_last_name
	 * @return self
	 */
	public function setAltClientLastName(string $client_last_name) : self {
		$this->alt_client_last_name = $client_last_name;
		return $this;
	}

	/**
	 * replaceBetweenTags function
	 *
	 * @param string $text
	 * @param string $starting_tag
	 * @param string $ending_tag
	 * @param string $new_content
	 * @return string
	 */
	private function replaceBetweenTags(string $text, string $starting_tag, string $ending_tag, string $new_content) : string {

		$start_pos = strpos($text, $starting_tag);

		if ($start_pos === false) return $text;
		
		$end_pos = strpos($text, $ending_tag, $start_pos + strlen($starting_tag));

		if ($end_pos === false) return $text;
		
		$before = substr($text, 0, $start_pos + strlen($starting_tag));
		$after = substr($text, $end_pos);
		
		return $before.$new_content.$after;

	}

	/**
	 * parseEmailContent function
	 *
	 * @param string $content
	 * @return string
	 */
	private function parseEmailContent(string $content) : string {
		
		$currency = $this->alt_currency_code ?? $this->invoice->currency->code;
		$client_first_name = $this->alt_client_first_name ?? $this->invoice->client_wt->first_name;
		$client_last_name = $this->alt_client_last_name ?? $this->invoice->client_wt->last_name;
		
		$payment_gateway_url = '';
		
		if((int) $this->invoice->payment_gateway !== PaymentGateway::NONE->value){
			$payment_gateway_url = URL::signedRoute('invoice.pay', ['uuid' => $this->invoice->uuid]);
		}
		

		$search = [
			'{$client_first_name}',
			'{$client_last_name}',
			'{$invoice_date}',
			'{$due_date}',
			'{$invoice_number}',
			'{$payment_url}'
		];

		$replace = [
			$client_first_name,
			$client_last_name,
			General::formatDateTime($this->invoice->invoice_date, (int) $this->invoice->timezone_offset_minutes, false, false),
			General::formatDateTime($this->invoice->due_date, (int) $this->invoice->timezone_offset_minutes, false, false),
			$this->invoice->invoice_number,
			$payment_gateway_url
		];

		if((int) $this->invoice->payment_gateway === PaymentGateway::NONE->value){
			$content = $this->replaceBetweenTags($content,  '[{online-payment-start}]', '[{online-payment-end}]', '');
		}

		$content = str_ireplace($search, $replace, $content);
		$content = str_ireplace('{$invoice_total}', $this->invoice->total.' '.$currency, $content);
		$content = str_ireplace('{$unpaid_balance}', $this->invoice->balance_due.' '.$currency, $content);
		$content = str_ireplace('[{online-payment-start}]', '', $content);
		$content = str_ireplace('[{online-payment-end}]', '', $content);
		
		return $content;

	}

	/**
	 * sendEmail function
	 *
	 * @return array
	 */
	public function getContent() : array {

		$pdf_path = $this->invoice->id.DIRECTORY_SEPARATOR.$this->invoice->pdf_file;
		
		if(!Storage::disk($this->disk)->exists($pdf_path)){
			Log::alert('Could not send invoice as an attachment. invoice #:'.$this->invoice->id);
			throw new Exception('Could not send invoice as an attachment.');
		}
		

		$attachments = [];

		$attachments[] = ['disk' => $this->disk, 'path' => $pdf_path];

		$xml_path = $this->invoice->id.DIRECTORY_SEPARATOR.$this->invoice->xml_file;

		if($this->invoice->xml_file !== ''){
			$attachments[] = ['disk' => $this->disk, 'path' => $xml_path];
		}


		$data = [
			'invoice'		=>  $this->invoice,
			'attachments'	=>  $attachments,
			'content'		=>	$this->parseEmailContent($this->invoice_content)
		];

		return $data;

	}
}