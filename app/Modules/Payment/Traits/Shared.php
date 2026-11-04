<?php

namespace App\Modules\Payment\Traits;

use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Notification;

trait Shared {
	
	/**
	 * sendPaymentFailedAdminEmail function
	 *
	 * @param string $subscription_id
	 * @param array $settings_data
	 * @param string $gateway_name
	 * @return void
	 */
	public function sendPaymentFailedAdminEmail(string $subscription_id, array $settings_data, string $gateway_name) : void {

		$recurring_invoice_data = $settings_data['webhook_data'];

		$message = 'Hello, '.$gateway_name.' could not complete a payment for subscription (ID: '.$subscription_id.'). Please contact the customer to resolve the issue.';
		$message .= "\n";
		$message .= "\nDetails are,\n";
		$message .= "System recurring invoice id: ".$recurring_invoice_data['id'];
		$message .= "\n";
		$message .= "Client: ".$recurring_invoice_data['first_name'].' '.$recurring_invoice_data['last_name'];

		$notification = app(Notification::class);
		$notification->notify((int) $recurring_invoice_data['company_id'], NotificationType::SUBSCRIPTION_PAYMENT_FAILED, 'Failed to charge subscription', $message, []);

	}

}