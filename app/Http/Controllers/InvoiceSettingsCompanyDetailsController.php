<?php

namespace App\Http\Controllers;

use App\DiClasses\ArrangedFields\CompanyDetailsFields;
use App\DiClasses\ArrangedFields\SettingsArrangedFields;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\AdditionalCompanyField;
use App\Models\SettingsSection;
use App\Services\SettingsArrangedFieldsService;
use App\Traits\SettingsDefault;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class InvoiceSettingsCompanyDetailsController extends Controller{

	public function show(Request $request) : mixed{
		$company_id = Sanitize::input($request->input('company_id'));
		return (new SettingsArrangedFields(new CompanyDetailsFields('invoice_company_details', 'id', 'id_column'), $request, $company_id))->fetchArrangedFieldsData();
	}

	
	public function saveOrUpdate(Request $request) : mixed{
		$company_id = Sanitize::input($request->input('company_id'));
		$settings_arranged_fields = new SettingsArrangedFields(new CompanyDetailsFields('invoice_company_details', 'id', 'id_column'), $request, $company_id);
		return $settings_arranged_fields->saveOrUpdate(AdditionalCompanyField::class, 'companies');
	}

}
