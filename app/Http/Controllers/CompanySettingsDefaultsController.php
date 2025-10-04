<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use Exception;
use Illuminate\Http\Request;

class CompanySettingsDefaultsController extends Controller{

	public function show(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));
		$company = General::fetchDefaultCompanyById($company_id);

		return [
			'invoice_terms' 	=> $company->invoice_terms,
			'invoice_footer'	=> $company->invoice_footer,
		];

	}

	public function saveOrUpdate(Request $request){

		$company_id = Sanitize::input($request->input('company_id'));

		try{

			$invoice_terms = '';
			if($request->filled('invoice_terms')){
				$invoice_terms = Sanitize::input($request->input('invoice_terms'));
			}

			$invoice_footer = '';
			if($request->filled('invoice_footer')){
				$invoice_footer = Sanitize::input($request->input('invoice_footer'));
			}

			$company = General::fetchDefaultCompanyById($company_id);

			$company->invoice_terms = $invoice_terms;
			$company->invoice_footer = $invoice_footer;

			if($company->save()){
				return response(['message' => 'Saved successfully', 'validity' => 'saved_success'], 200);
			}

		}catch(Exception $e){

			return General::wentWrong();

		}

	}

}
