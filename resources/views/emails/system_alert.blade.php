@extends('emails.layout')

@section('content')
	<tr>
		<td>
			<h3>{{ $data['title'] }}</h3>
		</td>
	</tr>
	<tr>
		<td>
			<p>{!! nl2br(e($data['message'])) !!}</p>
		</td>
	</tr>
@endsection