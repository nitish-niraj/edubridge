@extends('emails.layout')

@section('content')
	<h2 style="margin:0 0 12px; color:#0f172a;">Account Suspended</h2>

	<p style="margin:0 0 10px;">Hello {{ $user->name }},</p>
	<p style="margin:0 0 10px;">
		Your EduBridge account has been suspended due to violations of our community guidelines.
	</p>
	<p style="margin:0 0 10px;">If you believe this is a mistake, contact our support team at support@edubridge.com.</p>
@endsection
