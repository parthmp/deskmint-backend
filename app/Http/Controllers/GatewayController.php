<?php

namespace App\Http\Controllers;

use App\Enums\PaymentRequests\PaymentRequestStatus;
use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Models\PaymentRequest;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Modules\Payment\Enums\PaymentGateway;
use App\Services\Gateway\GatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Stripe\PaymentMethod;

class GatewayController extends Controller {

	public function __construct(
		private GatewayService $gateway_service
	){}

    public function showPaymentPage(Request $request, string $uuid){

		$uuid = Sanitize::input($uuid);
		
		$invoice = $this->gateway_service->fetchInvoiceByUuid($uuid);

		if(!$invoice){
			die('invalid request');
		}

		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);

		$is_paid = ((int) InvoiceStatus::PAID->value === (int) $invoice->status);
		$is_cancelled = ((int) InvoiceStatus::CANCELLED->value === (int) $invoice->status);

		$due_date = General::formatDateTime($invoice->due_date, $invoice->timezone_offset_minutes);

		return view('payment.payment_page', ['invoice' => $invoice, 'payment_method_name' => PaymentGateway::getLabelByValue((int) $invoice->payment_gateway), 'checkout_url' => $checkout_url, 'is_paid' => $is_paid, 'due_date' => $due_date, 'is_cancelled' => $is_cancelled]);

	}

	public function generateUrl(Request $request, string $uuid){

		$invoice = $this->gateway_service->fetchInvoiceByUuid($uuid);

		if(!$invoice){
			die('invalid request');
		}

		if((int) $invoice->status === (int) InvoiceStatus::PAID->value || (int) $invoice->status === (int) InvoiceStatus::CANCELLED->value){
			
			$due_date = General::formatDateTime($invoice->due_date, $invoice->timezone_offset_minutes);
			$is_paid = ((int) InvoiceStatus::PAID->value === (int) $invoice->status);
			$is_cancelled = ((int) InvoiceStatus::CANCELLED->value === (int) $invoice->status);
			return view('payment.payment_page', ['invoice' => $invoice, 'payment_method_name' => PaymentGateway::getLabelByValue((int) $invoice->payment_gateway), 'checkout_url' => '', 'is_paid' => $is_paid, 'is_cancelled' => $is_cancelled,'due_date' => $due_date]);
		}

		//check if payment url already exist for past 2 hours with same payment method, total.
		$existing_url = $this->gateway_service->fetchExistingPaymentUrl($invoice->id);
		
		if($existing_url === null){

			//generate url.
			$url = $this->gateway_service->generatePaymentUrl($invoice);
			
			if($url === '' || ($url === null && (int) $invoice->payment_gateway !== PaymentGateway::NONE->value)){
				return redirect('/pay-invoice/failure/'.$invoice->payment_gateway);
			}

			return redirect($url);

		}

		return redirect($existing_url);

	}

	public function failedToConnect(Request $request, int $payment_method){

		$payment_method = Sanitize::input($payment_method);
		if($payment_method == ''){
			die('invalid request');
		}

		$payment_method_name = PaymentGateway::getLabelByValue((int) $payment_method);
		if($payment_method_name === ''){
			die('invalid request');
		}

		return view('payment.url_generation_error', ['payment_method_name' => $payment_method_name]);
	}

	/**
	 * showRequestPage function
	 *
	 * @param PaymentRequest $pr
	 * @return View
	 */
	private function showRequestPage(PaymentRequest $pr) : View {

		$is_completed = (int) $pr->status === PaymentRequestStatus::COMPLETED->value;
		$is_cancelled = (int) $pr->status === PaymentRequestStatus::CANCELLED->value;

		$checkout_url = URL::signedRoute('payment_request.pay.checkout', ['uuid' => $pr->uuid]);

		return view('payment.payment_page_request', [
			'pr'					=>	$pr,
			'is_paid'				=>	$is_completed,
			'is_cancelled'			=>	$is_cancelled,
			'checkout_url'			=>	$checkout_url,
			'payment_method_name'	=> 	PaymentGateway::getLabelByValue((int) $pr->payment_gateway)
		]);

	}

	public function showPaymentPageForRequest(Request $request, string $uuid){

		$uuid = Sanitize::input($uuid);
		
		$pr = $this->gateway_service->fetchRequestByUuid($uuid);

		if(!$pr){
			die('invalid request');
		}

		return $this->showRequestPage($pr);

	}

	public function generateUrlForRequest(Request $request, string $uuid){

		$uuid = Sanitize::input($uuid);
		
		$pr = $this->gateway_service->fetchRequestByUuid($uuid);

		if(!$pr){
			die('invalid request');
		}

		if((int) $pr->status === PaymentRequestStatus::CANCELLED->value || (int) $pr->status === PaymentRequestStatus::COMPLETED->value){
			return $this->showRequestPage($pr);
		}

		$existing_url = $this->gateway_service->fetchExistingPaymentUrlForRequest($pr->id);
		
		if($existing_url === null){
			
			//generate url.
			$url = $this->gateway_service->generatePaymentUrl($pr);
			
			if($url === '' || ($url === null && (int) $pr->payment_gateway !== PaymentGateway::NONE->value)){
				return redirect('/pay-request/failure/'.$pr->payment_gateway);
			}

			return redirect($url);

		}

		return redirect($existing_url);

	}
}
