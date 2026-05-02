<?php

namespace App\Repositories\SettingsSection;

use App\Models\SettingsSection;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class SettingsSectionRepository{

	/**
	 * fetchCompanyDetailsAndAddress function
	 *
	 * @param integer $company_id
	 * @return Collection|null
	 */
	public function fetchCompanyDetailsAndAddress(int $company_id) : Collection|null {
		return SettingsSection::where('company_id', $company_id)->where(function ($query){
			$query->where('type', ISC_INVOICE_COMPANY_ADDRESS_TYPE)->orWhere('type', ISC_INVOICE_COMPANY_DETAILS_TYPE);
		})->get();
	}

	/**
	 * fetchSettings function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @param boolean $settings_only
	 * @return SettingsSection|array|null
	 */
	public function fetchSettings(int $company_id, string $type, bool $settings_only = false) : SettingsSection|array|null {
		
		$row = SettingsSection::where([['company_id', '=', $company_id], ['type', '=', $type]])->first();
		
		if(!$settings_only){
			return $row;
		}

		return json_decode($row->settings_json, true);

	}

	/**
	 * create function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @return SettingsSection
	 */
	public function createObj(int $company_id, string $type) : SettingsSection {

		$settings = new SettingsSection();
		$settings->company_id = $company_id;
		$settings->type = $type;

		return $settings;

	}

	/**
	 * updateByObj function
	 *
	 * @param string $json
	 * @param SettingsSection $setting_record
	 * @return boolean
	 */
	public function updateByObj(string $json, SettingsSection $setting_record) : bool {

		try{
			
			$setting_record->settings_json = $json;

			return $setting_record->save();

		}catch(Exception $e){
			throw new Exception('unable to update the record');
		}

	}

	/**
	 * getGateWayNames function
	 *
	 * @param integer $company_id
	 * @return array
	 */
	public function getGateWayNames(int $company_id) : array {
		
		$gateways = SettingsSection::select('type')->where('company_id', '=', $company_id)->where(function($query){
			$query->where('type', '=', PAYMENTS_PAYPAL_TYPE);
			$query->orwhere('type', '=', PAYMENTS_STRIPE_TYPE);
		})->get()->map(function($ele){
			return $ele->type === PAYMENTS_PAYPAL_TYPE ? ['text' => 'PayPal', 'value' => PAYMENT_PAYPAL] : ['text' => 'Stripe' ,'value' => PAYMENT_STRIPE];
		})->toArray();

		return array_merge([['text' => 'Cash', 'value' => PAYMENT_CASH], ['text' => 'Netbanking', 'value' => PAYMENT_NETBANKING]], $gateways);

	}

	/**
	 * destroy function
	 *
	 * @param integer $company_id
	 * @param string $type
	 * @return void
	 */
	public function destroy(int $company_id, string $type) : void {
		SettingsSection::where([['company_id', '=', $company_id], ['type', '=', $type]])->delete();
	}

	/**
	 * fetchResultsWithMultipleTypes function
	 *
	 * @param integer $company_id
	 * @param array $types
	 * @return array|null
	 */
	public function fetchResultsWithMultipleTypes(int $company_id, array $types) : ?array {
		return SettingsSection::where('company_id', '=', $company_id)->whereIn('type', $types)->get()->mapWithKeys(fn($s) => [$s->type => json_decode($s->settings_json, true)])->toArray();
	}

}