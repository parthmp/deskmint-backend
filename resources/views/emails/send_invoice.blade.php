@extends('emails.layout')

@section('content')
	<tr>
		<td>
			<p>{{ $mail_data['content'] }}</p>
		</td>
	</tr>
@endsection