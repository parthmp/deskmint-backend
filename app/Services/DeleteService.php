<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeleteService{

    public function deleteByIds(Request $request, string $model, string $type): array {
        
        $validator = Validator::make($request->all(), [
            'ids' 	=> 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        if($validator->fails()){
            return [['message' => 'Invalid IDs provided', 'validity' => 'invalid_ids'], config('global.error_code')];
        }

        try{

            $ids = $request->input('ids');
            
            $count = $model::whereIn('id', $ids)->count();
            
            if($count !== count($ids)){
                return [['message' => 'Some records not found', 'validity' => 'not_found'], 404];
            }

            $deleted = $model::whereIn('id', $ids)->delete();
            
            return [['message' => "$type(s) deleted successfully", 'validity' => 'delete_success'], 200];

        }catch(Exception $e){
           
            return [['message' => 'Failed to delete records', 'validity' => 'delete_failed'], 500];

        }
    }
}