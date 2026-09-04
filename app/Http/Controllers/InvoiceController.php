<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Http\Requests\Invoice\AddCreditOrPaymentRequest;
use App\Http\Requests\Invoice\FetchInvoiceRequest;
use App\Http\Requests\Invoice\InvoiceGenerationRequest;
use App\Http\Requests\Invoice\SearchCreditsRequest;
use App\Http\Requests\Product\AutoCompleteSearchRequest;
use App\Http\Requests\ToggleCancelRequest;
use App\Jobs\GenerateInvoiceJob;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\InvoicesCustomField;
use App\Modules\ArrangedDataTableColumns\ArrangedDataTableColumns;
use App\Modules\ArrangedDataTableColumns\Exceptions\InvalidDataProvidedException;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Services\Invoice\Exceptions\InvoiceException;
use App\Services\Invoice\InvoiceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

	public function fetchProducts(AutoCompleteSearchRequest $request){
		
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
					
					DB::afterCommit(function() use ($company_id, $invoice_id, $do_send) {
						GenerateInvoiceJob::dispatch($company_id, $invoice_id, $do_send);
					});
					
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
		
		if(!$this->invoice_service->ifInvoiceExists($invoice_id, $company_id)){
			return response(['message' => 'invalid data', 'validity' => 'invalid_data', 'tab_switch' => 0], config('global.error_code'));
		}

		try{

			$this->invoice_service->validateAllForInvoice($request, $company_id, $invoice_id);

			try{
				
				$do_send = (bool) Sanitize::input($request->input('settings.send_invoice_in_email'));

				DB::transaction(function() use ($request, $company_id, $do_send, $invoice_id) {

					$invoice_id = $this->invoice_service->save($request, $company_id, $invoice_id);


					DB::afterCommit(function() use ($company_id, $invoice_id, $do_send) {
						GenerateInvoiceJob::dispatch($company_id, $invoice_id, $do_send);
					});

				});

				$message = 'Invoice updated successfully';

				if($do_send){
					$message .= ' and invoice has been sent';
				}

				return response(['message' => $message, 'validity' => 'invoice_updated'], 200);

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

		$data = $request->validated();
		
		try{

			$invoice = $this->invoice_service->fetchInvoiceById((int) $data['invoice_id'], (int) $data['company_id']);
			
			if(!$invoice){
				return response(['message' => 'Invalid invoice provided', 'validity' => 'invalid_invoice'], config('global.error_code'));
			}

			if((int) $invoice->status === (int) InvoiceStatus::CANCELLED->value){
				return response(['message' => 'You can not send cancelled invoice', 'validity' => 'can_not_send_cancelled'], config('global.error_code'));
			}

			$this->invoice_service->markInvoiceSent((int) $data['company_id'], (int) $data['invoice_id']);

			DB::transaction(function() use ($request, $data) {

				DB::afterCommit(function() use ($data) {
					GenerateInvoiceJob::dispatch((int) $data['company_id'], (int) $data['invoice_id'], true);
				});

			});
			
			return response(['message' => 'Invoice sent successfully', 'validity' => 'invoice_sent'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}

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

		$invoice = $this->invoice_service->fetchInvoiceById((int) $invoice_id, (int) $company_id);
			
		if(!$invoice){
			return response(['message' => 'Invalid invoice provided', 'validity' => 'invalid_invoice'], config('global.error_code'));
		}

		$pdf_path = $invoice->id.DIRECTORY_SEPARATOR.$invoice->pdf_file;
		
		if(!Storage::disk(INVOICES_DISK)->exists($pdf_path)){
			Log::alert('Could not download. invoice #:'.$invoice->id);
			return response(['message' => 'File not found', 'validity' => 'file_not_found'], config('global.error_code'));
		}
		
		return Storage::disk(INVOICES_DISK)->download($pdf_path);

	}

	public function snapshot(GenericRequest $request, int $invoice_id){

		$invoice_id = (int) Sanitize::input($invoice_id);

		return $this->invoice_service->fetchSnapshot($invoice_id);


	}

	public function toggleCancel(ToggleCancelRequest $request){

		$data = $request->validated();

		$invoice = $this->invoice_service->fetchInvoiceById((int) $data['invoice_id'], (int) $data['company_id']);

		if(!$invoice){
			return response(['message' => 'Invalid invoice provided', 'validity' => 'invalid_invoice'], config('global.error_code'));
		}
		
		if((int) $invoice->status === (int) InvoiceStatus::PAID->value || (int) $invoice->status === (int) InvoiceStatus::PARTIALLY_PAID->value){
			return response(['message' => 'You can not change status for this invoice', 'validity' => 'status_change_blocked'], config('global.error_code'));
		}

		try{

			$this->invoice_service->updateInvoiceStatus((int) $data['invoice_id'], (int) $data['status']);
			GenerateInvoiceJob::dispatch($invoice->company_id, $invoice->id, false);
			return response(['message' => 'Updated successfully', 'validity' => 'status_change_success'], 200);

		}catch(Exception $e){
			return General::wentWrong();
		}
		
	}

	public function addCreditOrPayment(AddCreditOrPaymentRequest $request){

		$data = $request->validated();

		$type = (string) $data['type'];
		$payment_type = (int) $data['payment_type'];

		try{
			
			if($type === 'payment'){
				$this->invoice_service->ifPaymentNumberExists((int) $data['company_id'], (string) $data['uuid'], null);
				$this->invoice_service->validatePaymentType($payment_type);
			}else{
				$this->invoice_service->ifCreditNumberExists((int) $data['company_id'], (string) $data['uuid'], null);
			}

			$data = $this->invoice_service->addCreditOrPaymentForInvoice((int) $data['company_id'], (int) $data['invoice_id'], (string) $data['amount'], (int) $payment_type, (string) $data['uuid'], (string) $type);
			
			return response(['message' => 'Applied successfully', 'valdity' => 'applied_success', 'meta' => $data], 200);

		}catch(InvoiceException $e){
			return response(['message' => $e->getMessage(), 'valdity' => $e->getValidity()], $e->getCode());
		}catch(Exception $e){
			return General::wentWrong();
		}

		

	}

	public function ApplyUnapplyCreditsFetchInvoice(GenericRequest $request, int $id){

		$data = $request->validated();

		$invoice_id = Sanitize::input($id);

		return $this->invoice_service->fetchInvoiceForApplyUnapplyCredit((int) $data['company_id'], (int) $invoice_id);

	}

	public function ApplyUnapplyCreditsSearchCredits(SearchCreditsRequest $request){
		
		$data = $request->validated();

		

	}

}
