<?php

namespace App\Modules\Payment\Jobs;

use App\Models\RefundLog;
use App\Modules\Payment\Gateways\Payment;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;


class FetchPayPalRefundArn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

	public int $backoff = 3600;

    public function __construct(public string $uuid){}

    public function handle(): void {

		$refund_log = RefundLog::where('uuid', '=', $this->uuid)->with('currency')->first();

		if(!$refund_log){
			return ;
		}

        // Skip if ARN was already saved
        if($refund_log->ARN){
            return;
        }


		$payment = new Payment((int) $refund_log->company_id);
		$paypal = $payment->paypal((string) $refund_log->currency->code);
		$refund_details = $paypal->getRefundDetails($refund_log->gateway_refund_id);
		
		$error = $refund_details['error'] ?? null;

		if($error){
			throw new Exception('PayPal refund lookup failed: ' . json_encode($error));
		}

		$arn = $refund_details['acquirer_reference_number'] ?? null;

        if($arn){
            $refund_log->update(['ARN' => $arn]);
            return;
        }

        
       	if($this->attempts() < $this->tries){
			$this->release(now()->addHours(24));
		}

    }
}
