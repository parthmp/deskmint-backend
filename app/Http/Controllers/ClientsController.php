<?php

namespace App\Http\Controllers;

use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use Illuminate\Http\Request;

class ClientsController extends Controller{

	public function fetchClientsCustomFields(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = ClientsCustomField::where('company_id', '=', $company_id)->whereHas('customFieldType')->orderBy('order_on_add_edit_page', 'asc')->with('customFieldType')->get();

		return $fields;

	}

}
