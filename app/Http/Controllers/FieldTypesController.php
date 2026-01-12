<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Http\Requests\FieldType\CreateFieldTypesRequest;
use App\Http\Requests\GenericRequest;
use App\Models\CustomFieldType;
use App\Modules\DataTable\Requests\DataTableRequest;
use App\Services\DeleteService;
use App\Services\FieldType\FieldTypesService;
use Exception;
use Symfony\Component\HttpFoundation\Request;

class FieldTypesController extends Controller{

	public function __construct(private FieldTypesService $field_types_service, private DeleteService $delete_service){}
	
	public function getInputTypes(GenericRequest $request){

		return $this->field_types_service->fetchInputTypes();

	}

	public function store(CreateFieldTypesRequest $request){
		
		$data = $request->validated();

		if(!in_array($data['input_type'], config('global.field_types'))){
			return response(['message' => 'Invalid field provided', 'validity' => 'invalid_field'], config('global.error_code'));
		}

		try{
			
			if($this->field_types_service->create($data['input_type'], $data['input_name'])){
				return response(['message' => 'Custom field type created successfully', 'validity' => 'created_success'], 200);
			}

			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}

	}

	public function index(DataTableRequest $request){
		
		$data = $request->validated();
		return $this->field_types_service->fetch($data);

	}

	public function show(GenericRequest $request, int $id){

		try{
			$id = (int) Sanitize::input($id);
			return $this->field_types_service->fetchById($id);
		}catch(Exception $e){
			return response(['message' => 'Invalid request', 'validity' => 'invalid_request'], config('global.error_code'));
		}

	}

	public function update(CreateFieldTypesRequest $request, int $id){
		
		$id = (int) Sanitize::input($id);
		
		$data = $request->validated();
		
		if(!in_array($data['input_type'], config('global.field_types'))){
			return response(['message' => 'Invalid field provided', 'validity' => 'invalid_field'], config('global.error_code'));
		}

		try{

			$field = $this->field_types_service->fetchById($id);

			if($this->field_types_service->updateByObj($data['input_type'], $data['input_name'], $field)){
				return response(['message' => 'Custom field type updated successfully', 'validity' => 'updated_success'], 200);
			}

			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));

		}catch(Exception $e){
			return response(['message' => 'Something went wrong', 'validity' => 'something_wrong'], config('global.error_code'));
		}

	}

	public function destroy(Request $request){

		try{

			$response = $this->delete_service->deleteByIds($request, CustomFieldType::class, 'Field type');
			return response($response[0], $response[1]);

		}catch(Exception $e){

			return General::wentWrong();

		}

	}

}
