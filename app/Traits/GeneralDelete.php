<?php

namespace App\Traits;

use App\Helpers\Sanitize;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait GeneralDelete{

	public function deleteByIds(Request $request, string $model, string $type) : array{

		$ids = $request->input('ids');

		if(!is_array($ids) || empty($ids)){
			return [['message' => 'No valid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code')];
		}

		foreach ($ids as $id){
			if (!is_numeric($id)){
				return [['message' => 'All IDs must be numeric', 'validity' => 'non_numeric'], config('global.error_code')];
			}
		}

		
		$model::whereIn('id', $ids)->delete();
		return [['message' => $type.'(s) deleted successfully', 'validity' => 'delete_success'], 200];

	}

}