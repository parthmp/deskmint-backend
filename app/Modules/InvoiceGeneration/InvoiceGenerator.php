<?php

namespace App\Modules\InvoiceGeneration;

use App\Models\Invoice;
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
	 * @return Invoice
	 */
	private function fetchInvoiceData() : Invoice {
		return $this->invoice_db_operations->fetchInvoiceRow();
	}

	/**
	 * generateContextArrayForRenderer function
	 *
	 * @return array
	 */
	private function generateContextArrayForRenderer() : array {

		$context = [
			'general_settings'			=>	$this->invoice_settings_resolver->fetchGeneral(),
			'client_details_settings'	=>	$this->invoice_settings_resolver->fetchClientDetails(),
			'company_details_settings'	=>	$this->invoice_settings_resolver->fetchCompanyDetails(),
			'additional_company_fields'	=>	$this->invoice_db_operations->fetchAdditionalCompanyFields(),
			'company_address_settings'	=>	$this->invoice_settings_resolver->fetchCompanyAddressDetails(),
			'invoice_details_settings'	=>	$this->invoice_settings_resolver->fetchInvoiceDetails(),
			'invoice_data'				=>	$this->invoice_db_operations->fetchInvoiceRow(),
			'total_fields_settings'		=>	$this->invoice_settings_resolver->fetchTotalFieldsDetails()
		];

		$context['client_custom_fields_values'] = $this->invoice_db_operations->fetchCustomFieldValuesOfClient((int) $context['invoice_data']['client_id']);

		return $context;
	}

	private function modifyInvoiceTemplate(){
		$contents = $this->fetchTemplateContents();
		$renderer = new InvoiceRenderer($contents, $this->generateContextArrayForRenderer());
		return $renderer->render();
	}

	public function generateInvoice(){

		/* generate invoice here */
		//return $this->invoice_settings_resolver->fetchClientDetails();
		return $this->modifyInvoiceTemplate();

	}


}