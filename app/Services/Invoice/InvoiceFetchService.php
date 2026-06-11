<?php

namespace App\Services\Invoice;

use App\Helpers\Sanitize;
use App\Models\InvoicesCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Repositories\SettingsSection\SettingsSectionRepository;
use App\Services\HandleInvoiceNumbers;
use App\Services\Invoice\Exceptions\InvoiceException;
use Illuminate\Http\Request;

class InvoiceFetchService{

	public function __construct(
		private InvoiceValidationService $invoice_validation_service,
		private InvoiceSettingsService $invoice_settings_service,
		private CustomFields $custom_fields,
		private SettingsSectionRepository $settings_section_repository
	){}

	/**
	 * fetchInitialData function
	 *
	 * @param Request $request
	 * @param integer $company_id
	 * @return array
	 */
	public function fetchInitialData(Request $request, int $company_id) : array {
		
		if(!$this->invoice_validation_service->validateTimezoneOffeset($request)){
			throw new InvoiceException("Invalid request", "invalid_timezone", config('global.error_code'));
		}

		$timezone_offset_minutes = (int) Sanitize::input($request->input('timezone_offset_minutes'));

		$invoice_settings = $this->invoice_settings_service->setCompany($company_id);

		$fields = $this->custom_fields->fetchCustomFields(InvoicesCustomField::class, $company_id);

		// /* get payment integration data */
		$gateways = $this->settings_section_repository->getGateWayNames((int) $company_id);

		return [
			'invoice_number'	=>	(new HandleInvoiceNumbers((int) $company_id, $invoice_settings->getInvoiceNumbers(), (int) $timezone_offset_minutes))->getNextInvoiceNumber(),
			'product_columns' 	=> 	$invoice_settings->getProductColumns(),
			'total_fields' 		=> 	$invoice_settings->getTotalFields(),
			'custom_fields'		=>	$this->custom_fields->printCustomFields($fields),
			'gateways'			=>	$gateways
		];
		
	}

	public function fetchIndex(int $company_id){
		
		$validated = $this->client_validation_service->validateForIndex($request);

		if(!$validated){
			throw new ClientException("Invalid request", 'invalid_request', config('global.error_code'));
		}

		$company_id = Sanitize::input($request->input('company_id'));

		/* check custom fields showing fallback */
		$user_data = $this->arranged_database_operations->fetchUserIndexColumnDataByUserId($company_id, 'clients');
		
		if(!$user_data){
			$user_data = $this->arranged_database_operations->fetchSettingsIndexColumnDataByFeatureName($company_id, 'clients');
		}

		$searchable_columns = [];
		$show_columns = [];
		$searchable_dates = [];
		$clients_flat_columns = [];
		$date_only_columns = [];

		if($user_data){
			
			$user_data =  json_decode($user_data->columns_json, true);
			$clients_custom_columns = $this->custom_fields->fetchCustomFieldsArray(ClientsCustomField::class, $company_id);

			for($z = 0 ; $z < count($user_data) ; $z++){
				$temp_label2 = $user_data[$z]['label'];
				if($user_data[$z]['show'] === true){
					if($user_data[$z]['type'] === 'normal'){

						$temp_label = $this->processTempLabel($user_data[$z]['label']);

						$show_columns[] = [
							'label'	=>	$temp_label,
							'text'	=>	$user_data[$z]['text']
						];
						
						
					}else{
						$show_columns = array_merge($show_columns, $this->processClientCustomColumns($clients_custom_columns, $user_data[$z]['clients_custom_fields_id']));
					}
				}

				if($user_data[$z]['type'] === 'normal'){
					if($user_data[$z]['searchable'] === true){
						if($user_data[$z]['is_date'] === true){
							$searchable_dates[] = 'clients.'.$user_data[$z]['label'];
						}else{

							$searchable_columns[] = $this->processTempLabelForSearchables($temp_label2, $user_data[$z]['label']);
							
						}
						
					}
				}else{
					if($user_data[$z]['searchable'] === true){

						for($x = 0 ; $x < count($clients_custom_columns) ; $x++){

							if($user_data[$z]['clients_custom_fields_id'] === $clients_custom_columns[$x]['id']){

								$label_with_underscores = General::replaceWithUnderscores($clients_custom_columns[$x]['label']);

								$clients_flat_columns[] = 'clients_flat.'.$label_with_underscores.' as '.$label_with_underscores;

								if($user_data[$z]['is_date'] === true){
									$searchable_dates[] = 'clients_flat.'.$label_with_underscores;
								}else{
									$searchable_columns[] = 'clients_flat.'.$label_with_underscores;
								}
								
							}

						}

						
					}
				}

			}


		}else{

			array_push($searchable_columns, 'clients.first_name');
			array_push($searchable_columns, 'clients.last_name');
			array_push($searchable_columns, 'clients.email');
			array_push($searchable_dates, 'clients.created_at');

			array_push($show_columns, [
				'label'	=>	'first_name',
				'text'	=>	'First name',
			]);
			array_push($show_columns, [
				'label'	=>	'last_name',
				'text'	=>	'Last name',
			]);
			array_push($show_columns, [
				'label'	=>	'email',
				'text'	=>	'Email',
			]);
			array_push($show_columns, [
				'label'	=>	'created_at',
				'text'	=>	'Added on',
			]);

		}
		
		$clients_flat_columns = array_unique($clients_flat_columns);
		
		$data['searched_term'] = Sanitize::input($request->input('searched_term'));
		$data['current_page'] = Sanitize::input($request->input('current_page'));
		$data['sorted_column'] = $request->input('sorted_column');
		$data['default_per_page'] = Sanitize::input($request->input('default_per_page'));
		$data['per_page'] = $request->input('per_page') ? Sanitize::input($request->input('per_page')) : $data['default_per_page'];
		$data['date_range'] = $request->input('date_range');
		
		$fields = $this->processDataTable($clients_flat_columns, $data, $searchable_dates, $searchable_columns, $company_id);
		
		$rows = $fields->items();
		
		for($z = 0 ; $z < count($rows) ; $z++){
			
			foreach($rows[$z]->getAttributes() as $col_key => $col_val){
				
				if(!is_array($col_val) && General::isMySQLDateTime($col_val)){
					
					if(in_array($col_key, $date_only_columns)){
						$rows[$z]->{$col_key} = [
							'type' 	=> 'date',
							'text'	=>	Carbon::parse($col_val)->toISOString()
						];
					}else{
						$rows[$z]->{$col_key} = Carbon::parse($col_val)->toISOString();
					}

				}

				
			}
		 	
		}
		array_push($show_columns, [
			'label'	=>	'actions',
			'text'	=>	'Actions'
		]);
		$table_data = [
			'columns' => $show_columns,
			'rows' => $fields->items()
		];
		
		$total_pages = $fields->lastPage();

		return [
			'table_data'	=>		$table_data,
			'total_pages'	=>		$total_pages,
			'current_page'	=>		$fields->currentPage()
		];
	}

}