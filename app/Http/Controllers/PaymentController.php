<?php

namespace App\Http\Controllers;

use App\Helpers\General;
use App\Helpers\Sanitize;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{

	public function __construct(
		private PaymentService $payment_service
	){}

    public function showPaymentPage(Request $request, string $uuid){

		$uuid = Sanitize::input($uuid);

		$invoice = $this->payment_service->fetchInvoiceByUuid($uuid);

		if(!$invoice){
			dd('invalid request.');
		}

		$payment_method_name = General::getPaymentMethodName((int) $invoice->payment_method);

		return view('payment.payment_page', ['invoice' => $invoice, 'payment_method_name' => $payment_method_name]);
		
	}

	public function generateUrl(Request $request, string $uuid){
		return $uuid;
	}
}
