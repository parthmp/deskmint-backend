<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pay Invoice</title>
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
.pay-status {
  text-align: center;
  font-size: 13px;
  color: var(--ink-soft);
  margin: 14px 0 0;
}

button:disabled {
  background: #9db8ad;
  cursor: not-allowed;
}
</style>

</head>
<body>	
  
@yield('content')
</body>
<script>
  document.getElementById('pay-form').addEventListener('submit', function () {
    const button = document.getElementById('pay-button');
    const status = document.getElementById('pay-status');

    button.disabled = true;
    button.textContent = 'Processing…';
    status.hidden = false;
  });
</script>
</html>