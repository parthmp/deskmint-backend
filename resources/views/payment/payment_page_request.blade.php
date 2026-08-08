@extends('payment.payment_layout')
	
  <div class="card">
	@if($is_paid || $is_cancelled)

    <span class="badge badge--paid">
      
	  @if($is_paid)
      	Payment received
	  @endif
	  @if($is_cancelled)
      	Payment cancelled
	  @endif
    </span>

    <p class="invoice-number">#{{ $pr->uuid }}</p>
	@if($is_paid)
		<p class="paid-message">
		This request is already paid. No further action is needed.
		</p>
	@endif
	@if($is_cancelled)
		<p class="paid-message">
		This request is cancelled. No further action is needed.
		</p>
	@endif
  @else
    <span class="badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M4 12l5 5L20 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Payment request
    </span>

    <p class="invoice-number">#{{ $pr->uuid }}</p>

    <div class="amount-row">
      <span class="amount-label">Amount due</span>
      <span class="amount-value">{{ number_format($pr->amount, 2) }} {{ $pr->currency->code }}</span>
    </div>

    
    <div class="meta">
      <span>Paying via</span>
      <span>{{ $payment_method_name }}</span>
    </div>

    <form method="POST" action="{{ $checkout_url }}" id="pay-form">
		@csrf
		<p id="pay-status" class="pay-status" hidden>Redirecting you to {{ $payment_method_name }}, please wait…</p>
		<button type="submit" id="pay-button">Pay {{ number_format($pr->amount, 2) }} {{ $pr->currency->code }} </button>
		
	</form>



    <p class="footnote">Secured by DeskMint. You'll be redirected to {{ $payment_method_name }} to complete payment.</p>

	@endif
  </div>
