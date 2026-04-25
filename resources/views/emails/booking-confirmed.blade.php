@extends('emails.layout')

@section('content')
	<h2 style="margin:0 0 12px; color:#0f172a;">Session Confirmed</h2>

	<p style="margin:0 0 8px;">
		@if($recipientType === 'student')
			Your session with <strong>{{ $booking->teacher->name }}</strong> has been confirmed.
		@else
			A session with <strong>{{ $booking->student->name }}</strong> has been confirmed.
		@endif
	</p>

	<p style="margin:0 0 8px;"><strong>Date:</strong> {{ $booking->start_at->format('l, F j, Y') }}</p>
	<p style="margin:0 0 8px;"><strong>Time:</strong> {{ $booking->start_at->format('g:i A') }} – {{ $booking->end_at->format('g:i A') }}</p>
	<p style="margin:0 0 8px;"><strong>Subject:</strong> {{ $booking->subject ?? 'General' }}</p>
	<p style="margin:0 0 8px;"><strong>Price:</strong> {{ $booking->price > 0 ? '₹' . number_format($booking->price, 2) : 'Free' }}</p>

	<p style="margin:14px 0 0;">
		<a href="{{ config('app.url') . ($recipientType === 'student' ? '/student/bookings' : '/teacher/sessions') }}" style="display:inline-flex; min-height:40px; align-items:center; justify-content:center; padding:0 14px; border-radius:999px; text-decoration:none; background:#0d3b66; color:#fff; font-weight:700;">
			View My Sessions
		</a>
	</p>
@endsection
