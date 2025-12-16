<?php

namespace App\Modules\InvoiceGeneration;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceGenerator{

	private int $company_id;
	private int $invoice_id;
	private InvoiceSettingsResolver $invoice_settings_resolver;
	private InvoiceDBOperations $invoice_db_operations;
	
	/**
	 * __construct function
	 *
	 * @param integer $company_id
	 * @param integer $invoice_id
	 */
	public function __construct(int $company_id, int $invoice_id){
		$this->company_id = $company_id;
		$this->invoice_id = $invoice_id;
		$this->invoice_settings_resolver = new InvoiceSettingsResolver($company_id, $invoice_id);
		$this->invoice_db_operations = new InvoiceDBOperations($company_id, $invoice_id);
	}

	/**
	 * fetchTemplateContents function
	 *
	 * @return string
	 */
	private function fetchTemplateContents() : string {

		$general_settings = $this->invoice_settings_resolver->fetchGeneral();
		$template_name = strtolower($general_settings['template'].'.html');

		try{
			return Storage::disk('invoice_templates')->get($template_name);
		}catch(Exception $e){
			Log::error("Unable to read file : {template_name}", ['template_name' => $template_name]);
		}

	}

	/**
	 * fetchInvoiceData function
	 *
	 * @return Collection
	 */
	private function fetchInvoiceData() : Collection {
		return $this->invoice_db_operations->fetchInvoiceRow();
	}

	public function generateInvoice(){

		/* generate invoice here */
		

	}


}