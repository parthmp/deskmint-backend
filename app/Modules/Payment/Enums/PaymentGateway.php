<?php

namespace App\Modules\Payment\Enums;

use Illuminate\Support\Facades\DB;

enum PaymentGateway: int {

    case NONE = 1;
    case PAYPAL = 2;
    case STRIPE = 3;

	/**
	 * settingsType function
	 *
	 * @return string
	 */
    public function settingsType() : string {

        return match($this){
            self::NONE   	=> '',
            self::PAYPAL   	=> 'payments_paypal',
            self::STRIPE   	=> 'payments_stripe'
        };
    }

	/**
	 * label function
	 *
	 * @return string
	 */
    public function label(): string {

        return match ($this) {
            self::NONE   	=> 'None',
            self::PAYPAL   	=> 'PayPal',
            self::STRIPE   	=> 'Stripe'
        };
    }

	/**
	 * fromSettingsType function
	 *
	 * @param string $key
	 * @return self|null
	 */
    public static function fromSettingsType(string $key): ?self {

        foreach(self::cases() as $gateway){
            if($gateway->settingsType() === $key){
                return $gateway;
            }
        }

        return null;
    }

	/**
	 * configuredOptions function
	 *
	 * Returns [['text' => 'PayPal', 'value' => 1], ...] for gateways
	 *
	 * @return array
	 */
    public static function configuredOptions(): array {

        $gateways = array_filter(self::cases(), fn ($gateway) => $gateway !== self::NONE);

        $all_keys = array_map(fn ($gateway) => $gateway->settingsType(), $gateways);

        $existing_keys = DB::table('settings_section')->whereIn('type', $all_keys)->pluck('type')->toArray();

        $options = [
			[
				'text'	=>	self::NONE->label(),
				'value'	=>	self::NONE
			]
		];

        foreach ($gateways as $gateway) {
            if (in_array($gateway->settingsType(), $existing_keys, true)){
                $options[] = [
                    'text'  => $gateway->label(),
                    'value' => $gateway->value,
                ];
            }
        }

        return $options;
    }

    /**
	 * configured function
	 *
	 * @return array
	 */
    public static function configured(): array {

        $all_keys = array_map(fn ($gateway) => $gateway->settingsType(), self::cases());

        $existing_keys = DB::table('settings_section')->whereIn('type', $all_keys)->pluck('type')->toArray();

        return array_values(array_filter(
            self::cases(),
            fn ($gateway) => in_array($gateway->settingsType(), $existing_keys, true)
        ));

    }

	/**
	 * getAllValues function
	 *
	 * @return array
	 */
	public static function getAllValues(): array {
        return array_column(self::cases(), 'value');
    }

	/**
	 * paymentGatewayExists function
	 *
	 * @return boolean
	 */
	public static function paymentGatewayExists(int $gateway_number) : bool {
		return in_array($gateway_number, self::getAllValues());
	}

	/**
	 * getLabelByValue function
	 *
	 * @return string
	 */
	public static function getLabelByValue(int $value) : string {
		foreach(self::cases() as $case){
			if($case->value === $value){
				return $case->label();
			}
		}
		return '';
	}

	/**
	 * isConfigured function
	 *
	 * Check whether a given gateway value has a row in settings_section.
	 *
	 * @param int $value
	 * @return bool
	 */
    public static function isConfigured(int $value): bool {

        $gateway = self::tryFrom($value);

        if($gateway === null || $gateway === self::NONE){
            return false;
        }

        return DB::table('settings_section')->where('type', $gateway->settingsType())->exists();
    }

    /**
	 * options function
	 *
	 * @return array
	 */
    public static function options(): array {
        
		$options = [];

        foreach(self::cases() as $gateway){
            $options[$gateway->value] = $gateway->label();
        }

        return $options;
    }
}