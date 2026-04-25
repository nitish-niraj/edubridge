@extends('emails.layout')

@section('content')
	<h2 style="margin:0 0 12px; color:#0f172a;">Your Session Starts in 1 Hour</h2>

	<p style="margin:0 0 8px;">
		@if($recipientType === 'student')
			Your session with <strong>{{ $booking->teacher->name }}</strong> starts soon.
		@else
			Your session with <strong>{{ $booking->student->name }}</strong> starts soon.
		@endif
	</p>

	<p style="margin:0 0 8px;"><strong>Time:</strong> {{ $booking->start_at->format('g:i A') }} – {{ $booking->end_at->format('g:i A') }}</p>
	<p style="margin:0 0 8px;"><strong>Subject:</strong> {{ $booking->subject ?? 'General' }}</p>

	<p style="margin:14px 0 0;">
		<a href="{{ config('app.url') . '/session/' . $booking->id }}" style="display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:0 14px; border-radius:999px; text-decoration:none; background:#16a34a; color:#fff; font-weight:700;">
			Join Session Now
		</a>
	</p>
@endsection
