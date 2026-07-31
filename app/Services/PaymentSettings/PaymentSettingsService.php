<?php

namespace App\Services\PaymentSettings;

use App\Modules\Payment\Enums\PaymentGateway;
use App\Repositories\SettingsSection\SettingsSectionRepository;

/**
 * PaymentSettingsService class
 */
class PaymentSettingsService{

	public function __construct(private SettingsSectionRepository $settings_section_repository){}

	/**
	 * getGatewayNames function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function getGatewayNames(int $company_id) : array {
		return PaymentGateway::configuredOptions($company_id);
		//return $this->settings_section_repository->getGateWayNames($company_id);

	}

}