<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Invoice\FetchInvoiceRequest;
use App\Http\Requests\Invoice\InvoiceGenerationRequest;
use App\Http\Requests\Product\SearchProductRequest;
use App\Jobs\GenerateInvoiceJob;
use App\Models\Invoice;
use App\Models\InvoiceCustomFieldValue;
use App\Models\InvoicesCustomField;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\CustomFields\CustomFields;
use App\Modules\InvoiceGeneration\InvoiceGenerator;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Invoice\InvoiceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * InvoiceController class
 */
class InvoiceController extends Controller{

	private array $additional_fields = [
		[
			'label'			=>	'c_code',
			'text'			=>	'Currency'
		]
	];

	private array $date_fields = [
		'due_date',
		'invoice_date',
		'created_at'
	];

	public function __construct(
		private InvoiceService $invoice_service,
		private ArrangedDataTableColumns $arranged_data_table_columns
	){}
    

	public function searchClients(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$searched = (string) Sanitize::input($request->input('searched'));

		try{
			return $this->invoice_service->searchClientByName($company_id, $searched);
		}catch(Exception $e){
			return General::wentWrong();
		}
	}


	public function fetchInitialData(Request $request){

		$company_id = (int) Sanitize::input($request->input('company_id'));
	
		try{
			return $this->invoice_service->fetchInitialData($request, $company_id);
		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function fetchProducts(SearchProductRequest $request){
		
		$data = $request->validated();

		try{
			return $this->invoice_service->searchProductsByName($data['company_id'], $data['searched']);
		}catch(Exception $e){
			return General::wentWrong();
		}

		
	}

	public function fetchArrangedColumns(Request $request){
		return $this->arranged_data_table_columns->fetchArrangedColumnsData($request, 'invoices', 'invoices', InvoicesCustomField::class, 'invoice', remove_columns:['invoice_terms', 'send_email', 'pattern_matched', 'scan_chars', 'settings_snapshot', 'client_id', 'company_id', 'timezone_offset_minutes', 'currency_id', 'pdf_file', 'xml_file', 'uuid'], additional_fields: $this->additional_fields);
	}
	

	public function saveArrangedColumns(Request $request){

		try{
			$this->arranged_data_table_columns->saveArrangedColumnsData($request, InvoicesCustomField::class, 'invoices', 'invoices', 'invoice', $this->additional_fields, $this->date_fields);
			return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
		}catch(InvalidDataProvidedException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function index(Request $request){

		try{
			return $this->invoice_service->fetchIndex($request);
		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
		

	}
	
	/**
	 * store function
	 *
	 * @param Request $request
	 * @return void
	 */
	public function store(Request $request){
		
		$company_id = (int) Sanitize::input($request->input('company_id'));

		try{

			$this->invoice_service->validateAllForInvoice($request, $company_id);

			try{
				
				$do_send = (bool) Sanitize::input($request->input('settings.send_invoice_in_email'));

				DB::transaction(function() use ($request, $company_id, $do_send) {

					$invoice_id = $this->invoice_service->save($request, $company_id);
					
					//if($do_send){
						
						DB::afterCommit(function() use ($company_id, $invoice_id) {
							GenerateInvoiceJob::dispatch($company_id, $invoice_id, $this->invoice_service);
						});
					//}
					
				});
								
				$message = 'Invoice created successfully';

				if($do_send){
					$message .= ' and invoice has been sent';
				}

				return response(['message' => $message, 'validity' => 'invoice_created'], 200);

			}catch(Exception $e){
				return General::wentWrong();
			}
			
		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity(), 'tab_switch' => $e->getTab()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function update(Request $request, int $invoice_id){

		$company_id = (int) Sanitize::input($request->input('company_id'));
		$invoice_id = (int) Sanitize::input((string) $invoice_id);

		if(!$this->invoice_service->ifInvoiceExists($invoice_id)){
			return response(['message' => 'invalid data', 'validity' => 'invalid_data', 'tab_switch' => 0], config('global.error_code'));
		}

		try{

			$this->invoice_service->validateAllForInvoice($request, $company_id, $invoice_id);

			try{

				$do_send = (bool) Sanitize::input($request->input('settings.send_invoice_in_email'));

				DB::transaction(function() use ($request, $company_id, $do_send, $invoice_id) {

					$invoice_id = $this->invoice_service->save($request, $company_id, $invoice_id);

					//if($do_send){
						DB::afterCommit(function() use ($company_id, $invoice_id) {
							GenerateInvoiceJob::dispatch($company_id, $invoice_id, $this->invoice_service);
						});
					//}
					
				});

				$message = 'Invoice updated successfully';

				if($do_send){
					$message .= ' and invoice has been sent';
				}

				return response(['message' => $message, 'validity' => 'invoice_created'], 200);

			}catch(Exception $e){
				return General::wentWrong();
			}

		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity(), 'tab_switch' => $e->getTab()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function show(FetchInvoiceRequest $request, int $invoice_id){

		$data = $request->validated();

		try{
			
			$invoice_id = (int) Sanitize::input($invoice_id);
			return $this->invoice_service->fetchInvoice((int) $data['company_id'], (int) $invoice_id, (int) $data['timezone_offset_minutes']);

		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

		

	}

	public function destroy(Request $request){

		$ids = $request->input('ids');

		if(!$ids){
			return response(['message' => 'No valid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code'));
		}
		
		try{
			
			$ids = Sanitize::recursive($ids);

			$this->invoice_service->deleteInvoices($ids);
			return response(['message' => 'Invoice(s) deleted successfully', 'validity' => 'delete_success'], 200);

		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}
	}

	public function sendInvoice(InvoiceGenerationRequest $request){

		// $data = $request->validated();
		
		// try{
		// 	GenerateInvoiceJob::dispatch($data['company_id'], $data['invoice_id'], $this->invoice_service);
		// 	return response(['message' => 'Invoice sent successfully', 'validity' => 'invoice_sent'], 200);
		// }catch(Exception $e){
		// 	return General::wentWrong();
		// }

	}

	public function downloadPDF(InvoiceGenerationRequest $request){

		$data = $request->validated();
		
		$url = URL::temporarySignedRoute(
			'invoice.download', now()->addMinutes(5),
			[
				'invoice_id' 			=> $data['invoice_id'],
				'company_id' 			=> $data['company_id'],
				'time_offset_minutes' 	=> $data['time_offset_minutes']
			]
		);

		return response(['url' => $url], 200);

	}

	public function servePDF(Request $request){

		$company_id = (int) $request->query('company_id');
		$invoice_id = (int) $request->query('invoice_id');
		
		return app(InvoiceGenerator::class)
			->setCompanyId($company_id)
			->setInvoiceId($invoice_id)
			->exec()
			->generatePDF(save: false, add_random: true)
			->download();

	}

	public function snapshot(GenericRequest $request, int $invoice_id){

		$invoice_id = (int) Sanitize::input($invoice_id);

		return $this->invoice_service->fetchSnapshot($invoice_id);


	}

}
