@extends('payment.payment_layout')
	
  <div class="card">
	@if($is_paid)

    <span class="badge badge--paid">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M4 12l5 5L20 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Payment received
    </span>

    <p class="invoice-number">#{{ $invoice->invoice_number }}</p>

    <p class="paid-message">
      This invoice is already paid. No further action is needed.
    </p>

  @else
    <span class="badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M4 12l5 5L20 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Invoice ready
    </span>

    <p class="invoice-number">#{{ $invoice->invoice_number }}</p>

    <div class="amount-row">
      <span class="amount-label">Amount due</span>
      <span class="amount-value">{{ number_format($invoice->balance_due, 2) }} {{ $invoice->currency->code }}</span>
    </div>

    <div class="meta">
      <span>Due date</span>
      <span>{{ $due_date }}</span>
    </div>
    <div class="meta">
      <span>Paying via</span>
      <span>{{ $payment_method_name }}</span>
    </div>

    <form method="POST" action="{{ $checkout_url }}" id="pay-form">
		@csrf
		<p id="pay-status" class="pay-status" hidden>Redirecting you to {{ $payment_method_name }}, please wait…</p>
		<button type="submit" id="pay-button">Pay {{ number_format($invoice->balance_due, 2) }} {{ $invoice->currency->code }} </button>
		
	</form>



    <p class="footnote">Secured by DeskMint. You'll be redirected to {{ $payment_method_name }} to complete payment.</p>

	@endif
  </div>
