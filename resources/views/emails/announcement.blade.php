@extends('emails.layout')

@section('content')
	<h2 style="margin:0 0 12px; color:#0f172a;">{{ $announcement->title }}</h2>
	<div style="margin:0 0 10px;">
		{!! $announcement->message !!}
	</div>
@endsection
