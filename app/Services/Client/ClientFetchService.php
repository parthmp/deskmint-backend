<?php

namespace App\Services\Client;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\ClientsCustomField;
use App\Modules\CustomFields\CustomFields;
use App\Repositories\Currency\CurrencyRepository;
use App\Repositories\Industry\IndustryRepository;
use Illuminate\Http\Request;

/**
 * ClientFetchService class
 */
class ClientFetchService{

	/**
	 * __construct function
	 *
	 * @param CustomFields $custom_fields
	 * @param CurrencyRepository $currency_repository
	 * @param IndustryRepository $industry_repository
	 */
	public function __construct(private CustomFields $custom_fields, private CurrencyRepository $currency_repository, private IndustryRepository $industry_repository){}
	
	/**
	 * fetchCustomFields function
	 *
	 * @param Request $request
	 * @return array
	 */
	public function fetchCustomFields(Request $request) : array {

		$company_id = Sanitize::input($request->input('company_id'));

		$fields = $this->custom_fields->fetchCustomFields(ClientsCustomField::class, $company_id);

		$currencies = $this->currency_repository->fetchWithTextAndValue();

		$industries = $this->industry_repository->fetchWithTextAndValue();


		return [
					'data_fields' 	=>  $this->custom_fields->printCustomFields($fields),
					'countries'		=>	General::fetchCoutries(),
					'currencies'	=>	$currencies,
					'industries'	=>	$industries,
				];

	}

}