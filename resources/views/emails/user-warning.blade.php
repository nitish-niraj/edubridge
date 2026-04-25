@extends('emails.layout')

@section('content')
	<h2 style="margin:0 0 12px; color:#0f172a;">Community Guidelines Warning</h2>

	<p style="margin:0 0 10px;">Hello {{ $user->name }},</p>
	<p style="margin:0 0 10px;">We received a report regarding your activity and found the following concern:</p>

	<div style="margin:10px 0; padding:12px; border-radius:10px; background:#fff7ed; border:1px solid #fed7aa;">
		{{ $reason }}
	</div>

	<p style="margin:0;">Please review our community guidelines. Repeated violations may result in account suspension.</p>
@endsection
