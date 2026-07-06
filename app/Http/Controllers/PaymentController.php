<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Enums\InvoiceStatus;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PaymentController extends Controller
{

	public function __construct(
		private PaymentService $payment_service
	){}

    public function showPaymentPage(Request $request, string $uuid){

		$uuid = Sanitize::input($uuid);

		$invoice = $this->payment_service->fetchInvoiceByUuid($uuid);

		if(!$invoice){
			die('invalid request');
		}

		$payment_method_name = General::getPaymentMethodName((int) $invoice->payment_method);
		$checkout_url = URL::signedRoute('invoice.pay.checkout', ['uuid' => $invoice->uuid]);

		$is_paid = ((int) InvoiceStatus::PAID->value === (int) $invoice->status);
		$is_cancelled = ((int) InvoiceStatus::CANCELLED->value === (int) $invoice->status);

		$due_date = General::formatDateTime($invoice->due_date, $invoice->timezone_offset_minutes);

		return view('payment.payment_page', ['invoice' => $invoice, 'payment_method_name' => $payment_method_name, 'checkout_url' => $checkout_url, 'is_paid' => $is_paid, 'due_date' => $due_date, 'is_cancelled' => $is_cancelled]);

	}

	public function generateUrl(Request $request, string $uuid){

		$invoice = $this->payment_service->fetchInvoiceByUuid($uuid);

		if(!$invoice){
			die('invalid request');
		}

		if((int) $invoice->status === (int) InvoiceStatus::PAID->value || (int) $invoice->status === (int) InvoiceStatus::CANCELLED->value){
			$payment_method_name = General::getPaymentMethodName((int) $invoice->payment_method);
			$due_date = General::formatDateTime($invoice->due_date, $invoice->timezone_offset_minutes);
			$is_paid = ((int) InvoiceStatus::PAID->value === (int) $invoice->status);
			$is_cancelled = ((int) InvoiceStatus::CANCELLED->value === (int) $invoice->status);
			return view('payment.payment_page', ['invoice' => $invoice, 'payment_method_name' => $payment_method_name, 'checkout_url' => '', 'is_paid' => $is_paid, 'is_cancelled' => $is_cancelled,'due_date' => $due_date]);
		}

		//check if payment url already exist for past 2 hours with same payment method, total.
		$existing_url = $this->payment_service->fetchExistingPaymentUrl($invoice->id);

		if($existing_url === null){

			//generate url.
			$url = $this->payment_service->generatePaymentUrl($invoice);

			if($url === '' || ($url === null && $invoice->payment_method !== PAYMENT_CASH && $invoice->payment_method !== PAYMENT_NETBANKING)){
				return redirect('/pay-invoice/failure/'.$invoice->payment_method);
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

		$payment_method_name = General::getPaymentMethodName((int) $payment_method);
		if(!$payment_method_name){
			die('invalid request');
		}

		return view('payment.url_generation_error', ['payment_method_name' => $payment_method_name]);
	}
}
