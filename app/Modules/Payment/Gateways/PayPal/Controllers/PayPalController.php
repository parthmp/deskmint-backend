<?php

namespace App\Modules\Payment\Gateways\PayPal\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SettingsSection;
use App\Models\Transaction;
use App\Modules\Payment\Gateways\PayPal\PayPal;
use App\Modules\Payment\Payment;
use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalController extends Controller{

	public function __construct(private Payment $payment){}

    //test spgehetti code to see if it works as it should
	public function handlePaymentWebhook(Request $request){
		logger($request->all());
		$payload    = $request->all();
		$event_type = $payload['event_type'] ?? null;

		$order_id = match($event_type) {
			'CHECKOUT.ORDER.APPROVED'   => $payload['resource']['id'],
			'PAYMENT.CAPTURE.COMPLETED' => $payload['resource']['supplementary_data']['related_ids']['order_id'],
			default                     => null
		};

		$transaction = Transaction::where('token_id_identifier', '=', $order_id)->first();

		$settings = SettingsSection::where('type', '=', 'payments_paypal')->first();

		$settings = json_decode($settings->settings_json, true);

		$provider = new PayPalClient([
			'payment_action'	=>	'Sale',
			'currency'			=>	$transaction->invoice_wt->client_wt->currency->code,
			//'notify_url'		=>	env('APP_URL').PAYMENT_NOTIFICATION_URL,
			'notify_url'		=>	'',
			'validate_ssl'		=>	true,
			'mode'				=>	'sandbox',
			'locale'			=>	'en_US',
			'sandbox' => [
				'client_id'         => $settings['client_id'],
				'client_secret'     => decrypt($settings['secret']),
				'app_id'            => $settings['app_id']
			],
			'live' => [
				'client_id'         => $settings['client_id'],
				'client_secret'     => decrypt($settings['secret']),
				'app_id'            => $settings['app_id']
			],
		]);
		
		$provider->getAccessToken();
		$verified = $provider->verifyWebHook([
			'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
			'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
			'cert_url'          => $request->header('PAYPAL-CERT-URL'),
			'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
			'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
			'webhook_id'        => 'YOUR_WEBHOOK_ID_FROM_DASHBOARD', //had tested with real id here, it works.
			'webhook_event'     => $request->all(),
		]);

		if (!$verified) {
			return response('Unauthorized', 401);
		}

		if ($event_type === 'CHECKOUT.ORDER.APPROVED') {
			logger("====");
			logger("====");
			logger("VERIFICATION STATUS FOR CHECKOUT.ORDER.APPROVED -> ".json_encode($verified));
			logger("====");
			logger("====");
			
			$provider->capturePaymentOrder($order_id);
		}

		if ($event_type === 'PAYMENT.CAPTURE.COMPLETED') {
			logger("====");
			logger("====");
			logger("VERIFICATION STATUS FOR PAYMENT.CAPTURE.COMPLETED -> ".json_encode($verified));
			logger("====");
			logger("====");
		}

		return response('OK', 200);
		
	}

}
