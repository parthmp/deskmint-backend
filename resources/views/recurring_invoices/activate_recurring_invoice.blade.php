@extends('payment.payment_layout')
	
  <div class="card">
	@if($is_active)

    <span class="badge badge--paid">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M4 12l5 5L20 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      This subscription is already active.
    </span>

 
  @else
    <span class="badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M4 12l5 5L20 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Subscription ready
    </span>

    <p class="invoice-number">#{{ $uuid }}</p>

    <div class="amount-row">
      <span class="amount-label">Amount</span>
      <span class="amount-value">{{ number_format($total, 2) }} {{ $currency }}</span>
    </div>

   
    <div class="meta">
      <span>Paying via</span>
      <span>{{ $payment_gateway }}</span>
    </div>

    <form method="POST" action="{{ $checkout_url }}" id="pay-form">
		@csrf
		<p id="pay-status" class="pay-status" hidden>Redirecting you to {{ $payment_gateway }}, please wait…</p>
		<button type="submit" id="pay-button">Amount {{ number_format($total, 2) }} {{ $currency }} </button>
		
	</form>



    <p class="footnote">Secured by DeskMint. You'll be redirected to {{ $payment_gateway }} to activate the subscription.</p>

	@endif
  </div>

@section('title')
Active subscrription
@endsection