@extends('emails.layout')

@section('content')
	<tr>
		<td>
			<p>Hello, <br>The system failed to generate payment URL for payment gateway {{ $payment_gateway }}. Please check your credentials in Dashboard -> Settings -> Payments and click on Setup/Edit button to edit the details.</p>
		</td>
	</tr>
@endsection