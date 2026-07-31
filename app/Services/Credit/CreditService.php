<?php

namespace App\Services\Credit;

use App\Enums\Credits\CreditStatus;
use App\Models\Credit;
use App\Repositories\Country\CountryRepository;
use App\Repositories\Credit\CreditRepository;
use Illuminate\Support\Collection;

/**
 * CreditService class
 */
class CreditService {

	public function __construct(
		private CreditRepository $credit_repository
	){}

	/**
	 * create function
	 *
	 * @param integer $company_id
	 * @param integer $client_id
	 * @param string $amount
	 * @return Credit
	 */
	public function create(int $company_id, int $client_id, string $amount) : Credit {

		$currency_id = $this->credit_repository->fetchClientCurrencyId($company_id, $client_id);
		return $this->credit_repository->createOrUpdate($company_id, $client_id, $currency_id, $amount, $amount, CreditStatus::NOT_APPLIED->value);

	}

}