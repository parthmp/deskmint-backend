<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\GenericRequest;
use App\Models\CustomFieldType;
use App\Models\InvoicesCustomField;
use App\Modules\CustomFieldsFeature\CustomFieldsFeature;
use App\Modules\CustomFieldsFeature\Exceptions\InvalidFieldsException;
use App\Modules\CustomFieldsFeature\Exceptions\LabelCharException;
use App\Modules\CustomFieldsFeature\Exceptions\LabelFoundException;
use App\Modules\CustomFieldsFeature\Exceptions\RecordNotFoundException;
use App\Modules\CustomFieldsFeature\Requests\CreateCustomFieldsFeatureRequest;
use App\Modules\CustomFieldsFeature\Requests\DeleteCustomFieldsFeatureRequest;
use App\Modules\DataTable\Requests\DataTableRequest;
use Exception;

class InvoicesCustomFieldsController extends Controller{

	private string $custom_id_flag = 'invoices_custom_field_id';
	private string $model = InvoicesCustomField::class;

	public function __construct(private CustomFieldsFeature $custom_fields_feature){}

	public function fetchFieldTypes(GenericRequest $request){
		return $this->custom_fields_feature->setModel(CustomFieldType::class)->fetchFieldTypes();
	}

	public function store(CreateCustomFieldsFeatureRequest $request){
		try{

			$this->custom_fields_feature->setModel($this->model)->saveOrUpdateCustomField($request->validated(), 'invoice', true, ISC_INVOICE_DETAILS_TYPE, $this->custom_id_flag);

			return response(['message' => 'Custom field created successfully', 'validity' => 'created_success'], 200);

		}catch(InvalidFieldsException|LabelCharException|LabelFoundException $e){

			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], config('global.error_code'));

		}catch(Exception $e){

			return General::wentWrong();

		}
	}

	public function index(DataTableRequest $request){
		return $this->custom_fields_feature->setModel($this->model)->indexData($request->validated(), 'invoice');
	}

	public function show(GenericRequest $request, int $id){

		$data = $request->validated();
		$company_id = $data['company_id'];
		$id = (int) Sanitize::input($id);

		try{
			return $this->custom_fields_feature->setModel($this->model)->showData($company_id, $id);
		}catch(RecordNotFoundException $e){
			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], config('global.error_code'));
		}catch(Exception $e){
			return General::wentWrong();
		}

	}

	public function update(CreateCustomFieldsFeatureRequest $request, int $id){

		try{
			
			$this->custom_fields_feature->setModel($this->model)->updateData($request->validated(), 'invoice', $id, ISC_INVOICE_DETAILS_TYPE, $this->custom_id_flag);
			return response(['message' => 'Custom field updated successfully', 'validity' => 'updated_success'], 200);

		}catch(InvalidFieldsException|LabelCharException|LabelFoundException|RecordNotFoundException $e){

			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], config('global.error_code'));

		}catch(Exception $e){

			return General::wentWrong();

		}

	}

	public function destroy(DeleteCustomFieldsFeatureRequest $request){
		
		$data = $request->validated();
		$company_id = $data['company_id'];

		try{

			$this->custom_fields_feature->setModel($this->model)->destroyData($request->validated(), 'invoice', ISC_INVOICE_DETAILS_TYPE, $company_id, $this->custom_id_flag);
			return response(['message' => 'Custom field(s) deleted successfully', 'validity' => 'delete_success'], 200);

		}catch(InvalidFieldsException $e){

			return response(['message' => $e->getMessage(), 'validity' => $e->getValidity()], config('global.error_code'));

		}catch(Exception $e){

			return General::wentWrong();

		}
	}

}
