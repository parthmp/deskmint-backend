<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pay Invoice — {{ $invoice->invoice_number }}</title>
<style>
  :root {
    --ink: #16241f;
    --ink-soft: #4a5a54;
    --paper: #f7faf8;
    --card: #ffffff;
    --mint: #2f7a5f;
    --mint-soft: #e6f2ec;
    --border: #dde5e1;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: var(--paper);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--ink);
  }

  .card {
    width: 100%;
    max-width: 420px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 40px 32px;
    box-shadow: 0 1px 2px rgba(22, 36, 31, 0.04), 0 8px 24px rgba(22, 36, 31, 0.06);
  }

  .badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--mint);
    background: var(--mint-soft);
    padding: 6px 12px;
    border-radius: 999px;
    margin-bottom: 24px;
  }

  .badge svg { width: 12px; height: 12px; }

  h1 {
    font-size: 15px;
    font-weight: 500;
    color: var(--ink-soft);
    margin: 0 0 4px;
  }

  .invoice-number {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 28px;
  }

  .amount-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    margin-bottom: 28px;
  }

  .amount-label {
    font-size: 14px;
    color: var(--ink-soft);
  }

  .amount-value {
    font-size: 32px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
  }

  .meta {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: var(--ink-soft);
    margin-bottom: 4px;
  }

  .meta:last-of-type { margin-bottom: 28px; }

  button {
    width: 100%;
    padding: 15px 20px;
    font-size: 16px;
    font-weight: 600;
    color: #ffffff;
    background: var(--mint);
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: background 0.15s ease, transform 0.05s ease;
  }

  button:hover { background: #256a52; }
  button:active { transform: scale(0.99); }

  button:focus-visible {
    outline: 3px solid var(--mint);
    outline-offset: 2px;
  }

  .footnote {
    text-align: center;
    font-size: 12px;
    color: var(--ink-soft);
    margin-top: 20px;
  }

  @media (prefers-reduced-motion: reduce) {
    button { transition: none; }
  }

  @media (max-width: 400px) {
    .card { padding: 32px 24px; }
    .amount-value { font-size: 28px; }
  }
.badge--paid {
  color: var(--mint);
  background: var(--mint-soft);
}

.paid-message {
  font-size: 14px;
  color: var(--ink-soft);
  line-height: 1.5;
  margin: 0;
}
</style>
</head>
<body>
	
  <div class="card">
	@if($invoice->is_paid == 1)

    <span class="badge badge--paid">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M4 12l5 5L20 6" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Payment received
    </span>

    <p class="invoice-number">#{{ $invoice->invoice_number }}</p>

    <div class="amount-row">
      <span class="amount-label">Amount paid</span>
      <span class="amount-value">{{ $invoice->currency->code }} {{ number_format($invoice->balance_due, 2) }}</span>
    </div>

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
      <span class="amount-value">{{ $invoice->currency->code }} {{ number_format($invoice->balance_due, 2) }}</span>
    </div>

    <div class="meta">
      <span>Due date</span>
      <span>{{ $invoice->due_date->format('M j, Y') }}</span>
    </div>
    <div class="meta">
      <span>Paying via</span>
      <span>{{ $payment_method_name }}</span>
    </div>

    <form method="POST" action="{{ route('invoice.pay.checkout', $invoice->uuid) }}">
      @csrf
      <button type="submit">Pay {{ $invoice->currency->code }} {{ number_format($invoice->balance_due, 2) }}</button>
    </form>

    <p class="footnote">Secured by DeskMint. You'll be redirected to {{ $payment_method_name }} to complete payment.</p>

	@endif
  </div>

</body>
</html>