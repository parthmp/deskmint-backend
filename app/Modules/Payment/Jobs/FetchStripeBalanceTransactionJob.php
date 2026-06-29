<?php

namespace App\Modules\Payment\Jobs;

use App\Models\Transaction;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class FetchStripeBalanceTransactionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        private string $payment_intent_id,
        private int $transaction_id,
        private string $secret,
        private string $currency
    ){}

    public function handle() : void {
		
        $stripe = new StripeClient($this->secret);

        $payment_intent = $stripe->paymentIntents->retrieve($this->payment_intent_id, [
            'expand' => ['latest_charge.balance_transaction']
        ]);

        $balance_transaction = $payment_intent->latest_charge->balance_transaction;

        if(!$balance_transaction){
            throw new Exception('Balance transaction not yet available, retrying...');
        }

       	$gateway_fee = BigDecimal::of($balance_transaction->fee)->dividedBy(100, 2, RoundingMode::HalfUp);

		$net_amount = BigDecimal::of($balance_transaction->net)->dividedBy(100, 2, RoundingMode::HalfUp);
		
		if($balance_transaction->currency !== strtolower($this->currency)){
			$exchange_rate = BigDecimal::of($balance_transaction->exchange_rate);
			$gateway_fee = $gateway_fee->dividedBy($exchange_rate, 2, RoundingMode::HalfUp);
			$net_amount  = $net_amount->dividedBy($exchange_rate, 2, RoundingMode::HalfUp);
		}

        $transaction = Transaction::find($this->transaction_id);

        if(!$transaction){
            Log::error('FetchStripeBalanceTransactionJob: transaction not found', ['transaction_id' => $this->transaction_id]);
            return;
        }

		$captured_details = json_decode($transaction->payment_captured_details, true);
		$captured_details['balance_transaction'] = $balance_transaction->toArray();

       	$transaction->gateway_fees_amount = $gateway_fee->__toString();
		$transaction->received_amount = $net_amount->__toString();
		$transaction->payment_captured_details = json_encode($captured_details);
        $transaction->save();
    }

    public function failed(Exception $exception): void {
        Log::error('FetchStripeBalanceTransactionJob failed after all retries', [
            'payment_intent_id' => $this->payment_intent_id,
            'transaction_id'    => $this->transaction_id,
            'error'             => $exception->getMessage()
        ]);
    }
}