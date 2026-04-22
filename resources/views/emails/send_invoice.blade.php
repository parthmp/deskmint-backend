@extends('emails.layout')

@section('content')
	<tr>
		<td>
			<p>{!! nl2br(e($mail_data['content'])) !!}</p>
		</td>
	</tr>
@endsection