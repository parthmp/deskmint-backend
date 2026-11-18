@extends('payment.payment_layout')
	
<div class="card">
	<h2>Failed to connect with {{ $payment_method_name }}. Please contact the invoice sender.</h2>	
	@if(isset($error))
	<p>{{ $error }}</p>
	@endif
</div>

@section('title')
Failed
@endsection